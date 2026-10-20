<?php

namespace App\Console\Commands;

use App\Services\User\UserWhitelistService;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class WhitelistNonMemberAccess extends Command
{
    protected $signature = 'app:whitelist-non-member-access
                            {--app-id=10059 : 应用ID}
                            {--channel=huawei : 市场渠道}
                            {--app-version=2.5.20 : 应用版本}
                            {--since=2026-10-17 : 起始日期，从当天 00:00:00 起}
                            {--type=3 : 1屏蔽广告 2免费试用 3两者}
                            {--dry-run : 只统计，不写入、不刷新缓存}
                            {--force : 跳过确认直接写入}';

    protected $description = '把指定版本访问过的非会员 IP、设备写入白名单，来源为手动添加';

    public function handle(): int
    {
        $appId = (int) $this->option('app-id');
        $channel = (string) $this->option('channel');
        $version = (string) $this->option('app-version');
        $since = $this->since();
        $type = (int) $this->option('type');
        $dryRun = (bool) $this->option('dry-run');

        if (!in_array($type, [1, 2, 3], true)) {
            $this->error('type 只能是 1、2、3');

            return self::FAILURE;
        }

        $db = DB::connection();
        $database = $db->selectOne('SELECT DATABASE() AS db')->db;
        $remark = "{$channel} {$version} {$since} 起非会员批量加入";

        $this->info("database={$database} app={$appId} channel={$channel} version={$version} since={$since} type={$type}");

        $this->prepareVipTables($db, $appId, $channel, $version, $since);
        [$ipRows, $deviceRows, $vipUuid, $guestUuid] = $this->collect($db, $appId, $channel, $version, $since);

        $existsIp = $this->existing($db, 'ip', $appId, $channel, $version);
        $existsDevice = $this->existing($db, 'device', $appId, $channel, $version);
        $newIp = array_values(array_filter($ipRows, fn ($row) => !isset($existsIp[$row->content])));
        $newDevice = array_values(array_filter($deviceRows, fn ($row) => !isset($existsDevice[$row->content])));

        $this->info("会员uuid={$vipUuid} 非会员uuid={$guestUuid}");
        $this->info('IP 待写入 ' . count($newIp) . '，已存在 ' . (count($ipRows) - count($newIp)));
        $this->info('设备 待写入 ' . count($newDevice) . '，已存在 ' . (count($deviceRows) - count($newDevice)));
        $this->line('IP样例: ' . implode(', ', array_map(fn ($row) => $row->content, array_slice($newIp, 0, 5))));
        $this->line('设备样例: ' . implode(', ', array_map(fn ($row) => $row->content, array_slice($newDevice, 0, 5))));

        if ($dryRun) {
            $this->info('预览结束，未写入');

            return self::SUCCESS;
        }

        if ($newIp === [] && $newDevice === []) {
            $this->info('没有需要写入的数据');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('确认写入白名单并刷新缓存？')) {
            $this->info('已取消');

            return self::SUCCESS;
        }

        $now = now()->toDateTimeString();
        $db->transaction(function () use ($db, $newIp, $newDevice, $appId, $channel, $version, $type, $remark, $now) {
            $this->insertRows($db, 'ip', $newIp, $appId, $channel, $version, $type, $remark, $now);
            $this->insertRows($db, 'device', $newDevice, $appId, $channel, $version, $type, $remark, $now);
        });

        if ($newIp !== []) {
            UserWhitelistService::cacheForIp();
        }
        if ($newDevice !== []) {
            UserWhitelistService::cacheForDevice();
        }

        $this->info('完成，缓存已刷新');

        return self::SUCCESS;
    }

    private function since(): string
    {
        $since = trim((string) $this->option('since'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            return $since . ' 00:00:00';
        }

        return $since;
    }

    private function prepareVipTables(Connection $db, int $appId, string $channel, string $version, string $since): void
    {
        $db->unprepared('SET SESSION group_concat_max_len = 8192');
        $db->unprepared('DROP TEMPORARY TABLE IF EXISTS tmp_wm_vip_uuid');
        $db->unprepared('DROP TEMPORARY TABLE IF EXISTS tmp_wm_vip_ip');
        $db->unprepared('DROP TEMPORARY TABLE IF EXISTS tmp_wm_vip_device');
        $db->unprepared("CREATE TEMPORARY TABLE tmp_wm_vip_uuid (uuid varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB");
        $db->unprepared("CREATE TEMPORARY TABLE tmp_wm_vip_ip (ip varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB");
        $db->unprepared("CREATE TEMPORARY TABLE tmp_wm_vip_device (device varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB");

        [$scope, $bind] = $this->scope($appId, $channel, $version, $since);
        $db->affectingStatement(
            "INSERT IGNORE INTO tmp_wm_vip_uuid (uuid)
             SELECT DISTINCT l.uuid
             FROM user_access_log l
             INNER JOIN users u ON u.app_id = l.app_id AND u.uuid = l.uuid AND u.is_vip = 1
             WHERE {$scope}",
            $bind
        );
        $db->affectingStatement(
            "INSERT IGNORE INTO tmp_wm_vip_ip (ip)
             SELECT DISTINCT l.ip FROM user_access_log l
             INNER JOIN tmp_wm_vip_uuid v ON v.uuid = l.uuid
             WHERE {$scope} AND l.ip <> ''",
            $bind
        );
        $db->affectingStatement(
            "INSERT IGNORE INTO tmp_wm_vip_device (device)
             SELECT DISTINCT l.device FROM user_access_log l
             INNER JOIN tmp_wm_vip_uuid v ON v.uuid = l.uuid
             WHERE {$scope} AND l.device <> '' AND CHAR_LENGTH(l.device) <= 64",
            $bind
        );
    }

    private function collect(Connection $db, int $appId, string $channel, string $version, string $since): array
    {
        [$scope, $bind] = $this->scope($appId, $channel, $version, $since);
        $where = "{$scope} AND v.uuid IS NULL";

        $vipUuid = (int) $db->selectOne('SELECT COUNT(*) AS c FROM tmp_wm_vip_uuid')->c;
        $guestUuid = (int) $db->selectOne(
            "SELECT COUNT(DISTINCT l.uuid) AS c
             FROM user_access_log l
             LEFT JOIN tmp_wm_vip_uuid v ON v.uuid = l.uuid
             WHERE {$where}",
            $bind
        )->c;

        $ipRows = $db->select(
            "SELECT l.ip AS content,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.device = '' OR CHAR_LENGTH(l.device) > 64, NULL, l.device) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_device,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.region = '', NULL, l.region) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_region
             FROM user_access_log l
             LEFT JOIN tmp_wm_vip_uuid v ON v.uuid = l.uuid
             LEFT JOIN tmp_wm_vip_ip vi ON vi.ip = l.ip
             WHERE {$where} AND l.ip <> '' AND vi.ip IS NULL
             GROUP BY l.ip",
            $bind
        );
        $deviceRows = $db->select(
            "SELECT l.device AS content,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.ip = '', NULL, l.ip) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_ip,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.region = '', NULL, l.region) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_region
             FROM user_access_log l
             LEFT JOIN tmp_wm_vip_uuid v ON v.uuid = l.uuid
             LEFT JOIN tmp_wm_vip_device vd ON vd.device = l.device
             WHERE {$where} AND l.device <> '' AND CHAR_LENGTH(l.device) <= 64 AND vd.device IS NULL
             GROUP BY l.device",
            $bind
        );

        return [$ipRows, $deviceRows, $vipUuid, $guestUuid];
    }

    private function existing(Connection $db, string $way, int $appId, string $channel, string $version): array
    {
        return $db->table('user_whitelist')
            ->where('way', $way)
            ->where('app_id', $appId)
            ->where('market_channel', $channel)
            ->where('version', $version)
            ->pluck('content')
            ->flip()
            ->all();
    }

    private function insertRows(Connection $db, string $way, array $rows, int $appId, string $channel, string $version, int $type, string $remark, string $now): void
    {
        $written = 0;
        foreach (array_chunk($rows, 300) as $chunk) {
            $payload = [];
            foreach ($chunk as $row) {
                $payload[] = [
                    'app_id' => $appId,
                    'platform' => '',
                    'market_channel' => $channel,
                    'way' => $way,
                    'content' => $row->content,
                    'version' => $version,
                    'source_ip' => $way === 'ip' ? $row->content : (string) ($row->source_ip ?? ''),
                    'source_region' => (string) ($row->source_region ?? ''),
                    'source_device' => $way === 'device' ? $row->content : (string) ($row->source_device ?? ''),
                    'type' => $type,
                    'source' => 1,
                    'remark' => $remark,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $db->table('user_whitelist')->insert($payload);
            $written += count($payload);
            $this->info("{$way} 已写入 {$written}");
        }
    }

    private function scope(int $appId, string $channel, string $version, string $since): array
    {
        return [
            "l.app_id = ? AND l.market_channel = ? AND l.version = ? AND l.created_at >= ? AND l.uuid <> ''",
            [$appId, $channel, $version, $since],
        ];
    }
}
