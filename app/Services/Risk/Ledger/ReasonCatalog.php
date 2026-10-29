<?php

namespace App\Services\Risk\Ledger;

class ReasonCatalog
{
    public const LABELS = [
        'emulator' => '模拟器',
        'hook' => 'Hook 注入',
        'root' => '已 Root',
        'vpn' => '正在使用 VPN',
        'cloud_phone' => '云手机',
        'monkey' => '自动化点击（Monkey）',
        'user_ca' => '安装了用户证书',
        'path_unsafe' => '安装路径异常',
        'debugger' => '正在调试',
        'adb_control_port' => 'ADB 调试端口开着',
        'proxy' => '开了系统代理',
        'jailbroken' => '已越狱',
        'multi_open' => '多开环境',
        'screen_captured' => '正在录屏',
        'screen_projection' => '正在投屏',
    ];

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }

    public static function normalizeList($reasons): array
    {
        if (is_string($reasons) && $reasons !== '') {
            $reasons = explode('+', $reasons);
        }
        if (!is_array($reasons)) {
            return [];
        }
        $codes = [];
        foreach ($reasons as $reason) {
            $code = strtolower(trim((string) $reason));
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
