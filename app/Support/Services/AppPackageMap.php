<?php

namespace App\Support\Services;

use App\Models\SystemApp;
use Illuminate\Support\Facades\File;

/**
 * 包名到应用 ID 的本地映射。
 *
 * 请求热路径只读 config/app_package_map.php（不入库，线上/本地各自生成）。
 * Redis 每次 GET 对这个量级也不是瓶颈，但包名几乎不变，写进配置更合适。
 * 映射未命中时才查库，避免 config:cache 或新应用尚未写入文件时解密失败。
 */
class AppPackageMap
{
    public static function idByPackage(?string $packageName): ?int
    {
        $packageName = trim((string) $packageName);
        if ($packageName === '') {
            return null;
        }

        $map = config('app_package_map', []);
        if (isset($map[$packageName]) && is_numeric($map[$packageName])) {
            return (int) $map[$packageName];
        }

        try {
            $id = SystemApp::query()
                ->where('package_name', $packageName)
                ->where('is_del', 0)
                ->value('id');
        } catch (\Throwable $exception) {
            return null;
        }

        return $id ? (int) $id : null;
    }

    public static function rebuild(): int
    {
        $map = [];
        SystemApp::query()
            ->where('is_del', 0)
            ->where('package_name', '!=', '')
            ->orderBy('id')
            ->get(['id', 'package_name'])
            ->each(function ($row) use (&$map) {
                $packageName = trim((string) $row['package_name']);
                if ($packageName !== '') {
                    $map[$packageName] = (int) $row['id'];
                }
            });

        $export = var_export($map, true);
        File::put(config_path('app_package_map.php'), <<<PHP
<?php

/**
 * 包名 -> 应用 ID。
 *
 * 由应用新增/修改包名时重写，供 Device-Env 密钥派生和新客户端解析 App-Id。
 * 热路径只读本数组，不走 Redis。
 */
return {$export};

PHP
        );

        config(['app_package_map' => $map]);

        return count($map);
    }
}
