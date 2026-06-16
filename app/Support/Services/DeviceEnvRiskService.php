<?php

namespace App\Support\Services;

use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceEnvRiskService
{
    /**
     * 客户端为了降低明文字段暴露，会把探针 JSON 的字段名压缩成短键后再加密。
     * 服务端评分和业务策略都使用语义化字段名，所以解密后第一步就是按这张表还原。
     * 注意：ts/nc/ver 是密文内元数据，客户端没有混淆，但统一放在这里便于递归还原。
     */
    private const WIRE_KEY_MAP = [
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
        'rk' => 'root_suspect',
        'jb' => 'is_jailbroken',
        'px' => 'has_proxy',
        'rt' => 'probe_variant',
        'tc' => 'touch_sample_count',
        'tt' => 'touch_timing_entropy',
        'td' => 'touch_coord_dispersion',
        'sg' => 'sensor_static_score',
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

            return $this->context('ok', $probe, [
                'package_name' => $packageName,
                'app_id' => $appId,
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
        $decision = $this->score($probe);

        return array_merge($meta, [
            'status' => $status,
            'probe' => $probe,
            'score' => $decision['score'],
            'reasons' => $decision['reasons'],
            'decision' => $decision,
        ]);
    }

    private function score(array $probe): array
    {
        $score = 0;
        $reasons = [];

        $add = function (bool $condition, int $points, string $reason) use (&$score, &$reasons): void {
            if ($condition) {
                $score += $points;
                $reasons[] = $reason;
            }
        };

        // 评分表来自 SERVER_RISK_INTEGRATION.md：
        // P0 环境信号（Monkey、Hook、用户 CA、模拟器、VPN 等）权重高；
        // 触摸、传感器、SIM 等软信号只加分，不单独决定拦截。
        $add((bool) ($probe['is_monkey'] ?? false), 100, 'monkey');
        $add((bool) ($probe['hook_suspect'] ?? false), 80, 'hook');
        $add((bool) ($probe['has_user_ca'] ?? false), 60, 'user_ca');
        $add(array_key_exists('path_safe', $probe) && $probe['path_safe'] === false, 50, 'path_unsafe');
        $add((bool) ($probe['is_emulator'] ?? false), 50, 'emulator');
        $add((bool) ($probe['is_vpn'] ?? false), 50, 'vpn');
        $add((bool) ($probe['is_debugger'] ?? false), 50, 'debugger');
        $add((int) ($probe['automation_proc_count'] ?? 0) >= 1, 40, 'automation_proc');
        $add((int) ($probe['touch_timing_entropy'] ?? 100) < 20 && (int) ($probe['touch_coord_dispersion'] ?? 100) < 15, 35, 'touch_low_entropy');
        $add((int) ($probe['sensor_static_score'] ?? 0) > 85, 25, 'sensor_static');
        $add((int) ($probe['sim_state'] ?? 0) === 1 && ($probe['network_transport'] ?? '') === 'wifi', 20, 'sim_absent_wifi');
        $add((bool) ($probe['root_suspect'] ?? false), 15, 'root');
        $add((bool) ($probe['has_proxy'] ?? false), 10, 'proxy');
        $add((bool) ($probe['is_jailbroken'] ?? false), 15, 'jailbroken');
        $add((int) ($probe['suspicious_a11y_count'] ?? 0) >= 1, 10, 'suspicious_a11y');
        $add(array_key_exists('drm_ok', $probe) && $probe['drm_ok'] === false, 10, 'drm_failed');

        // env_allows_ads=false 表示客户端本地硬门禁已经关闭广告。
        // 即使单项分数没有凑够 60，服务端也要同步进入 compliance_mode，保证 AI/广告策略一致。
        if (array_key_exists('env_allows_ads', $probe) && $probe['env_allows_ads'] === false && $score < 60) {
            $score = 60;
            $reasons[] = 'env_blocks_ads';
        }

        // 当前阈值：>=60 下发 compliance_mode=1 且关广告；40-59 只关广告。
        // 后续如果需要运营动态调整，可以把这段阈值迁到配置表，但输出字段保持不变。
        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'compliance_mode' => $score >= 60 ? 1 : 0,
            'ad_switch' => $score >= 40 ? 0 : 1,
        ];
    }
}
