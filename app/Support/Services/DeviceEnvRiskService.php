<?php

namespace App\Support\Services;

use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceEnvRiskService
{
    /**
     * Device-Env 的核心处理顺序：
     *
     * wire header -> HMAC 验签 -> AES 解密 -> 短键还原 -> 防重放
     * -> probe_v=9 schema 校验 -> 环境/行为组合评分 -> 风控决策
     *
     * 本服务只生成风险上下文，不直接写数据库。落库由 RiskProbeAuditService 负责，
     * /app/info 的响应覆盖由 applyAppInfoPolicy() 负责，以保持解密、存储、业务策略解耦。
     *
     * 客户端为了降低明文字段暴露，会把字段名压缩成短键后再整体加密。
     * 服务端统一使用语义化字段名评分，因此解密后必须先按此表还原。
     */
    private const WIRE_KEY_MAP = [
        // probe_v=9 schema 与原生协议自检。
        'ev' => 'env_schema_v',
        'ef' => 'env_field_count',
        'eg' => 'env_payload_digest',
        'pv' => 'probe_v',
        'pf' => 'platform',
        'mk' => 'is_monkey',
        'db' => 'is_debugger',
        'uc' => 'has_user_ca',
        'ps' => 'path_safe',
        'hk' => 'hook_suspect',
        'ac' => 'suspicious_a11y_count',
        'ss' => 'sim_state',
        'dr' => 'drm_ok',
        'ap' => 'automation_proc_count',
        'np' => 'native_probe_ok',
        'nv' => 'native_protocol_v',
        'nq' => 'native_channel_ok',
        'nm' => 'native_method_ok',

        // 设备环境、自动化与系统完整性探针。
        'rk' => 'root_suspect',
        'cp' => 'is_cloud_phone',
        'ao' => 'adb_control_port_open',
        'sd' => 'sensor_data_dead',
        'bf' => 'build_emulator_fingerprint',
        'to' => 'touch_coordinate_oob',
        'ns' => 'input_source_not_touchscreen',
        'sc' => 'screen_is_captured',
        'pj' => 'screen_projection_active',
        'rd' => 'release_debuggable',
        'bu' => 'build_type_not_user',
        'jb' => 'is_jailbroken',
        'px' => 'has_proxy',
        'rt' => 'probe_variant',

        // 完整版行为探针。tp/tv/... 是旧客户端别名，pr/cv/... 是当前 v9 短键。
        'tc' => 'touch_sample_count',
        'tt' => 'touch_timing_entropy',
        'td' => 'touch_coord_dispersion',
        'tp' => 'touch_pressure_variation',
        'tv' => 'touch_contact_variation',
        'tz' => 'touch_pressure_zero_ratio',
        'cc' => 'click_sample_count',
        'ct' => 'click_target_entropy',
        'cb' => 'click_center_bias',
        'ci' => 'click_in_bounds_ratio',
        'ws' => 'swipe_speed_uniformity',
        'kc' => 'click_sample_count',
        'ke' => 'click_target_entropy',
        'kb' => 'click_center_bias',
        'kr' => 'click_in_bounds_ratio',
        'pr' => 'touch_pressure_variation',
        'cv' => 'touch_contact_variation',
        'pz' => 'touch_pressure_zero_ratio',
        'wc' => 'swipe_sample_count',
        'wl' => 'swipe_linearity',
        'wu' => 'swipe_speed_uniformity',
        'wj' => 'swipe_micro_jitter',
        'gy' => 'gyro_static_score',
        'iu' => 'imu_static_during_touch',
        'sg' => 'sensor_static_score',
        'gs' => 'gyro_static_score',
        'it' => 'imu_static_during_touch',

        // 环境结论与服务端广告配置回传状态。
        'em' => 'is_emulator',
        'vp' => 'is_vpn',
        'nt' => 'network_transport',
        'ea' => 'env_allows_ads',
        'er' => 'env_block_reason',
        'ra' => 'remote_ad_config_applied',
        'rs' => 'remote_ad_switch',
        'rf' => 'remote_is_free_ad',
        'cm' => 'remote_compliance_mode',
        'ca' => 'client_allows_ads',

        // probe_v=8 轻量客户端兼容字段。
        'sim' => 'is_simulator',
        'vpn' => 'vpn_connected',
        'tcn' => 'touch_count',
        'ucc' => 'ui_click_count',
        'swc' => 'swipe_count',
        'tpm' => 'touch_pressure_avg_milli',
        'ist' => 'imu_samples_during_touch',

        // 密文内部协议元数据；nc 只用于防重放，落库前会移除明文。
        'ts' => 'ts',
        'nc' => 'nc',
        'ver' => 'ver',
    ];

    public function inspect(Request $request): array
    {
        // Device-Env 与业务接口混淆是两套机制：
        // 1. 业务接口别名、请求/响应字段映射仍由后台 api_obfuscation profile 管理；
        // 2. Device-Env 是每次请求携带的环境探针密文，只在这里解析成风控上下文。
        $sealed = trim((string) $request->header('Device-Env', ''));
        $packageName = trim((string) $request->header('Package-Name', ''));
        $appId = trim((string) $request->header('App-Id', ''));

        if ($sealed === '') {
            // 首版策略：缺失或解析失败不直接阻断业务，只打标为 missing/error。
            // /app/info 只会在 status=ok 且评分命中阈值时覆盖广告策略，避免误伤老版本或异常网络。
            return $this->context('missing', [], [
                'missing' => true,
                'package_name' => $packageName,
                'app_id' => $appId,
            ]);
        }

        try {
            if ($packageName === '' || $appId === '') {
                throw new RuntimeException('missing identity headers');
            }

            // 密钥派生必须使用请求头里的 Package-Name + App-Id，不能写死单个 App 的密钥。
            // 这样多个 App 共享同一套网关时，Device-Env 仍能按应用隔离。
            $probe = $this->decrypt($sealed, $packageName, $appId);
            $this->assertReplayAllowed($probe, $packageName, $appId);

            // schema 异常只记分和留痕，不直接拒绝真实用户请求。
            $validation = $this->validateProbeSchema($probe);

            return $this->context('ok', $probe, [
                'package_name' => $packageName,
                'app_id' => $appId,
                'validation' => $validation,
            ]);
        } catch (RuntimeException $e) {
            return $this->context('error', [], [
                'error' => $e->getMessage(),
                'package_name' => $packageName,
                'app_id' => $appId,
            ]);
        }
    }

    public function applyAppInfoPolicy(array $data, ?array $riskContext): array
    {
        if (($riskContext['status'] ?? null) !== 'ok') {
            return $data;
        }

        // 这里只对 /app/info 的最终 data 做保守覆盖，不修改配置中心、应用配置或白名单来源。
        // 高风险时服务端下发 compliance_mode=1 + ad_switch=0，与客户端本地门禁保持一致：
        // 即使后台原配置打开广告，客户端也会因为 compliance_mode=1 而不初始化/展示广告。
        $decision = $riskContext['decision'] ?? [];
        $complianceMode = (int) ($decision['compliance_mode'] ?? 0);
        $adSwitch = (int) ($decision['ad_switch'] ?? 1);

        if ($complianceMode === 1) {
            $data['compliance_mode'] = 1;
            $data['complianceMode'] = 1;
        } elseif (!array_key_exists('compliance_mode', $data) && !array_key_exists('complianceMode', $data)) {
            $data['compliance_mode'] = 0;
        }

        if ($adSwitch === 0) {
            $data['ad_switch'] = 0;
        } elseif (!array_key_exists('ad_switch', $data)) {
            $data['ad_switch'] = 1;
        }

        if (!array_key_exists('is_free_ad', $data)) {
            $data['is_free_ad'] = 0;
        }

        return $data;
    }

    private function decrypt(string $sealed, string $packageName, string $appId): array
    {
        // 线传格式固定为：{formatVer}.{hmacHex64}.{base64Cipher}
        // 只按前两个点拆分，避免 Base64 密文未来出现特殊字符时影响解析。
        $parts = explode('.', $sealed, 3);
        if (count($parts) !== 3) {
            throw new RuntimeException('invalid device env format');
        }

        [$formatVersion, $hmacHex, $base64Cipher] = $parts;
        if ($formatVersion !== '1') {
            throw new RuntimeException('unsupported device env version');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $hmacHex)) {
            throw new RuntimeException('invalid device env hmac');
        }

        $keys = $this->deriveKeys($packageName, $appId);
        // HMAC 覆盖的是 Base64 密文本身，而不是解码后的二进制密文；
        // 这需要和 Flutter 端保持完全一致，否则会出现“能解密但验签失败”的联调问题。
        $expected = hash_hmac('sha256', $base64Cipher, $keys['sign_key']);
        if (!hash_equals($expected, strtolower($hmacHex))) {
            throw new RuntimeException('device env sign mismatch');
        }

        $cipherText = base64_decode($base64Cipher, true);
        if ($cipherText === false) {
            throw new RuntimeException('invalid device env base64');
        }

        $json = openssl_decrypt($cipherText, 'AES-256-CBC', $keys['aes_key'], OPENSSL_RAW_DATA, $keys['aes_iv']);
        if ($json === false) {
            throw new RuntimeException('device env decrypt failed');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid device env json');
        }

        return $this->restoreProbeKeys($decoded);
    }

    private function deriveKeys(string $packageName, string $appId): array
    {
        // 与客户端文档保持一致：
        // root     = SHA256("device_env_v1|{packageName}|{appId}")
        // aes_key  = SHA256(root || "|aes")[0:32]
        // aes_iv   = SHA256(root || "|iv")[0:16]
        // sign_key = SHA256(root || "|sign")
        $root = hash('sha256', "device_env_v1|{$packageName}|{$appId}", true);

        return [
            'aes_key' => substr(hash('sha256', $root . '|aes', true), 0, 32),
            'aes_iv' => substr(hash('sha256', $root . '|iv', true), 0, 16),
            'sign_key' => hash('sha256', $root . '|sign', true),
        ];
    }

    private function restoreProbeKeys(array $payload): array
    {
        $probe = [];
        foreach ($payload as $key => $value) {
            $mappedKey = self::WIRE_KEY_MAP[$key] ?? $key;
            $probe[$mappedKey] = is_array($value) ? $this->restoreProbeKeys($value) : $value;
        }

        return $probe;
    }

    private function assertReplayAllowed(array $probe, string $packageName, string $appId): void
    {
        // ts 使用秒级 Unix 时间。窗口默认 300 秒，允许客户端和服务器有轻微时间偏差。
        $timestamp = (int) ($probe['ts'] ?? 0);
        $nonce = (string) ($probe['nc'] ?? '');
        $window = (int) config('api_obfuscation.device_env.timestamp_window_seconds', 300);

        if ($timestamp <= 0 || abs(time() - $timestamp) > $window) {
            throw new RuntimeException('device env expired');
        }

        if ($nonce === '') {
            throw new RuntimeException('missing device env nonce');
        }

        // nc 在 TTL 内只能消费一次，防止抓到的 Device-Env 被复制到后续请求重放。
        // cache key 同时包含 package/appId，避免不同应用之间 nonce 碰撞互相影响。
        $ttl = (int) config('api_obfuscation.device_env.nonce_ttl_seconds', 600);
        $prefix = (string) config('api_obfuscation.device_env.nonce_cache_prefix', 'device_env:nc:');
        $cacheKey = $prefix . $packageName . ':' . $appId . ':' . sha1($nonce);
        if (!Cache::add($cacheKey, 1, $ttl)) {
            throw new RuntimeException('replayed device env');
        }
    }

    private function context(string $status, array $probe, array $meta): array
    {
        // 统一返回结构放到 request attributes 中，后续控制器、日志或审计任务都可以复用。
        // status=missing/error 时 probe 为空，score 会自然为 0，不会触发广告降级。
        $decision = $this->score($probe, $meta['validation']['errors'] ?? []);

        return array_merge($meta, [
            'status' => $status,
            'probe' => $probe,
            'score' => $decision['score'],
            'reasons' => $decision['reasons'],
            'decision' => $decision,
        ]);
    }

    private function score(array $probe, array $validationErrors = []): array
    {
        $score = 0;
        $reasons = [];

        $add = function (bool $condition, int $points, string $reason) use (&$score, &$reasons): void {
            if ($condition) {
                $score += $points;
                $reasons[] = $reason;
            }
        };

        /*
         * 第一组：环境与自动化信号。
         * P0 信号权重高，通常客户端已经通过 env_allows_ads=false 本地关广告；
         * 服务端继续评分是为了同步 compliance_mode、记录原因和进行设备维度统计。
         */
        $add((bool) ($probe['is_monkey'] ?? false), 100, 'monkey');
        $add((bool) ($probe['hook_suspect'] ?? false), 80, 'hook');
        $add((bool) ($probe['has_user_ca'] ?? false), 60, 'user_ca');
        $add(array_key_exists('path_safe', $probe) && $probe['path_safe'] === false, 50, 'path_unsafe');
        $add((bool) ($probe['is_emulator'] ?? false), 50, 'emulator');
        $add((bool) ($probe['is_cloud_phone'] ?? false), 50, 'cloud_phone');
        $add((bool) ($probe['is_vpn'] ?? false), 50, 'vpn');
        $add((bool) ($probe['is_debugger'] ?? false), 50, 'debugger');
        $add((bool) ($probe['adb_control_port_open'] ?? false), 80, 'adb_control_port');
        $add((bool) ($probe['touch_coordinate_oob'] ?? false), 80, 'touch_coordinate_oob');
        $add((bool) ($probe['sensor_data_dead'] ?? false), 70, 'sensor_data_dead');
        $add((bool) ($probe['screen_is_captured'] ?? false), 70, 'screen_captured');
        $add((bool) ($probe['screen_projection_active'] ?? false), 70, 'screen_projection');
        $add((bool) ($probe['build_emulator_fingerprint'] ?? false), 50, 'emulator_fingerprint');
        $add((bool) ($probe['input_source_not_touchscreen'] ?? false), 60, 'input_not_touchscreen');
        $add((bool) ($probe['release_debuggable'] ?? false), 60, 'release_debuggable');
        $add((bool) ($probe['build_type_not_user'] ?? false), 50, 'build_type_not_user');
        $add((int) ($probe['automation_proc_count'] ?? 0) >= 1, 40, 'automation_proc');

        $touchSamples = (int) ($probe['touch_sample_count'] ?? 0);
        $clickSamples = (int) ($probe['click_sample_count'] ?? 0);
        $swipeSamples = (int) ($probe['swipe_sample_count'] ?? 0);

        /*
         * 第二组：人机行为组合。
         * 行为指标本身都是统计值，不应单项封禁。必须先满足样本量，再同时命中多个指标：
         * - 固定节奏 + 固定坐标；
         * - 压力全零 + 压力/接触面积低变化；
         * - 操作期间 IMU 与陀螺仪同时异常静止；
         * - 高直线、高匀速且几乎无微抖动；
         * - 多次点击集中在少量目标且高度贴近中心。
         */
        $add($touchSamples >= 5 && (int) ($probe['touch_timing_entropy'] ?? 100) < 20 && (int) ($probe['touch_coord_dispersion'] ?? 100) < 15, 35, 'touch_low_entropy');
        $add(
            $touchSamples >= 5
            && (int) ($probe['touch_pressure_zero_ratio'] ?? 0) > 80
            && (int) ($probe['touch_pressure_variation'] ?? 100) < 15
            && (int) ($probe['touch_contact_variation'] ?? 100) < 15,
            30,
            'touch_biometrics_synthetic'
        );
        $add(
            ($touchSamples >= 5 || $clickSamples >= 3)
            && (int) ($probe['imu_static_during_touch'] ?? 0) > 85
            && (int) ($probe['gyro_static_score'] ?? 0) > 85,
            30,
            'imu_static_during_activity'
        );
        $add(
            $swipeSamples >= 2
            && (int) ($probe['swipe_linearity'] ?? 0) > 92
            && (int) ($probe['swipe_speed_uniformity'] ?? 0) > 85
            && (int) ($probe['swipe_micro_jitter'] ?? 100) < 10,
            25,
            'swipe_synthetic'
        );
        $add((int) ($probe['click_sample_count'] ?? 0) >= 3 && (int) ($probe['click_target_entropy'] ?? 100) < 25 && (int) ($probe['click_center_bias'] ?? 0) > 80, 20, 'click_synthetic');
        $add((int) ($probe['sensor_static_score'] ?? 0) > 85, 25, 'sensor_static');
        $add((int) ($probe['sim_state'] ?? 0) === 1 && ($probe['network_transport'] ?? '') === 'wifi', 20, 'sim_absent_wifi');
        $add((bool) ($probe['root_suspect'] ?? false), 15, 'root');
        $add((bool) ($probe['has_proxy'] ?? false), 10, 'proxy');
        $add((bool) ($probe['is_jailbroken'] ?? false), 15, 'jailbroken');
        $add((int) ($probe['suspicious_a11y_count'] ?? 0) >= 1, 10, 'suspicious_a11y');
        $add(array_key_exists('drm_ok', $probe) && $probe['drm_ok'] === false, 10, 'drm_failed');
        $add(in_array('native_probe_protocol_mismatch', $validationErrors, true), 15, 'native_probe_protocol_mismatch');
        $add(in_array('device_env_digest_mismatch', $validationErrors, true), 10, 'device_env_digest_mismatch');
        $add(in_array('env_field_count_mismatch', $validationErrors, true), 5, 'env_field_count_mismatch');

        // 客户端已经执行硬门禁时，服务端至少提升到合规阈值，保证广告与 AI 策略一致。
        $complianceThreshold = (int) config('api_obfuscation.device_env.compliance_score_threshold', 60);
        if (array_key_exists('env_allows_ads', $probe) && $probe['env_allows_ads'] === false && $score < $complianceThreshold) {
            $score = $complianceThreshold;
            $reasons[] = 'env_blocks_ads';
        }

        // 总分保持在 100 分制；阈值可通过环境变量调整，不需要修改评分代码。
        $score = min(100, $score);
        $adBlockThreshold = (int) config('api_obfuscation.device_env.ad_block_score_threshold', 40);

        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'compliance_mode' => $score >= $complianceThreshold ? 1 : 0,
            'ad_switch' => $score >= $adBlockThreshold ? 0 : 1,
        ];
    }

    private function validateProbeSchema(array $probe): array
    {
        /*
         * probe_v=9 增加了字段数、稳定 JSON 摘要和原生协议自检。
         * 这些字段用于发现客户端接入漂移或探针被篡改。校验失败会进入 validation.errors，
         * 再由 score() 保守加分；不会在这里抛异常，以免客户端版本差异直接中断业务。
         */
        $errors = [];
        $digestOk = null;
        $fieldCountOk = null;

        if ((int) ($probe['probe_v'] ?? 0) >= 9) {
            if ((int) ($probe['env_schema_v'] ?? 0) !== 2) {
                $errors[] = 'env_schema_v_invalid';
            }

            $excluded = ['env_schema_v', 'env_field_count', 'env_payload_digest', 'ts', 'nc', 'ver'];
            $actualCount = count(array_filter(
                $probe,
                fn($value, $key) => $value !== null && !in_array($key, $excluded, true),
                ARRAY_FILTER_USE_BOTH
            ));
            if (is_int($probe['env_field_count'] ?? null)) {
                $fieldCountOk = $probe['env_field_count'] === $actualCount;
                if (!$fieldCountOk) {
                    $errors[] = 'env_field_count_mismatch';
                }
            }

            $digest = $probe['env_payload_digest'] ?? null;
            if (is_string($digest) && preg_match('/^[a-f0-9]{16}$/', $digest)) {
                $digestPayload = array_diff_key($probe, array_flip(['env_payload_digest', 'ts', 'nc', 'ver']));
                $expected = substr(hash('sha256', $this->canonicalJson($digestPayload)), 0, 16);
                $digestOk = hash_equals($expected, $digest);
                if (!$digestOk) {
                    $errors[] = 'device_env_digest_mismatch';
                }
            }

            if (($probe['native_probe_ok'] ?? null) === false) {
                $errors[] = 'native_probe_protocol_mismatch';
            }
            if (($probe['native_channel_ok'] ?? null) === false) {
                $errors[] = 'native_channel_mismatch';
            }
            if (($probe['native_method_ok'] ?? null) === false) {
                $errors[] = 'native_method_mismatch';
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'env_digest_ok' => $digestOk,
            'env_field_count_ok' => $fieldCountOk,
        ];
    }

    private function canonicalJson(array $payload): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (!is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }

            $value = array_filter($value, fn($item) => $item !== null);
            ksort($value, SORT_STRING);
            return array_map($normalize, $value);
        };

        return json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
