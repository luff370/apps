<?php

namespace App\Console\Commands;

use App\Models\SystemApp;
use App\Services\User\UserWhitelistService;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class WhitelistNonMemberAccess extends Command
{
    private const LEGACY_REMARK = 'huawei 2.5.20 2026-10-17 00:00:00 起非会员批量加入';

    protected $signature = 'app:whitelist-non-member-access
                            {--app-id=10059 : 应用ID}
                            {--channel=huawei : 市场渠道}
                            {--app-version=2.5.20 : 应用版本}
                            {--since=2026-10-17 : 起始日期，从当天 00:00:00 起}
                            {--type=3 : 1屏蔽广告 2免费试用 3两者}
                            {--dry-run : 只统计，不删除、不写入、不刷新缓存}
                            {--delete-only : 只删除本命令已写入的批次和回填日志，不重新写入}
                            {--force : 跳过确认直接执行}';

    protected $description = '删除上一批非会员白名单，按起始时间后的新增用户重写 IP、设备白名单';

    public function handle(): int
    {
        $appId = (int) $this->option('app-id');
        $channel = (string) $this->option('channel');
        $version = (string) $this->option('app-version');
        $since = $this->since();
        $type = (int) $this->option('type');
        $dryRun = (bool) $this->option('dry-run');
        $platform = $this->platform($channel);
        $remark = "{$channel} {$version} {$since} 起新增用户批量加入";
        $remarks = array_values(array_unique([self::LEGACY_REMARK, $remark]));

        if (!in_array($type, [1, 2, 3], true)) {
            $this->error('type 只能是 1、2、3');

            return self::FAILURE;
        }

        DB::disableQueryLog();
        $db = DB::connection();
        $database = $db->selectOne('SELECT DATABASE() AS db')->db;
        $this->info("database={$database} app={$appId} channel={$channel} version={$version} since={$since} type={$type}");
        $this->line("新增用户与统计一致：user_uuids 每个 uuid 一行，created_at 为首次出现；app={$appId} 渠道 {$channel}，created_at >= {$since}。不看 users 注册表。IP、设备取这些 uuid 在 {$version} 的访问");

        foreach ($remarks as $item) {
            $this->line("删除备注：{$item}");
        }
        $removing = $this->rowsToRemove($db, $appId, $channel, $version, $remarks);
        foreach ($removing as $row) {
            $this->warn("将删除 {$row->way} {$row->c} 条，创建时间 {$row->min_at} ~ {$row->max_at}");
        }
        if ($removing === []) {
            $this->info('没有需要删除的旧批次');
        }

        if ($this->option('delete-only')) {
            return $this->deleteOnly($db, $appId, $channel, $version, $since, $remarks, $removing, $dryRun);
        }

        $this->prepareNewUsers($db, $appId, $channel, $since);
        [$ipRows, $deviceRows, $newUuid] = $this->collect($db, $appId, $channel, $version, $since);
        $removingIp = $this->removingContents($db, 'ip', $appId, $channel, $version, $remarks);
        $removingDevice = $this->removingContents($db, 'device', $appId, $channel, $version, $remarks);
        $existsIp = array_diff_key($this->existing($db, 'ip', $appId, $channel, $version), $removingIp);
        $existsDevice = array_diff_key($this->existing($db, 'device', $appId, $channel, $version), $removingDevice);
        $newIp = array_values(array_filter($ipRows, fn ($row) => !isset($existsIp[$row->content])));
        $newDevice = array_values(array_filter($deviceRows, fn ($row) => !isset($existsDevice[$row->content])));
        $logCount = $this->accessCount($db, $appId, $channel, $version, $since);

        $this->info("新增uuid={$newUuid}");
        $this->info('IP 待写入 ' . count($newIp) . '，已存在 ' . (count($ipRows) - count($newIp)));
        $this->info('设备 待写入 ' . count($newDevice) . '，已存在 ' . (count($deviceRows) - count($newDevice)));
        $this->info("访问日志将回填约 {$logCount} 条访问，IP 和设备各一份，用来补最后访问、应用信息和访问统计");
        $this->line('IP样例: ' . implode(', ', array_map(fn ($row) => $row->content, array_slice($newIp, 0, 5))));
        $this->line('设备样例: ' . implode(', ', array_map(fn ($row) => $row->content, array_slice($newDevice, 0, 5))));

        if ($dryRun) {
            $this->info('预览结束，未删除、未写入');

            return self::SUCCESS;
        }

        if ($removing === [] && $newIp === [] && $newDevice === []) {
            $this->info('没有需要处理的数据');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('确认删除上一批并按新增用户重新写入？')) {
            $this->info('已取消');

            return self::SUCCESS;
        }

        $limits = $this->logLimits($db);
        $now = now()->toDateTimeString();
        $db->transaction(function () use ($db, $removingIp, $removingDevice, $newIp, $newDevice, $appId, $channel, $version, $platform, $type, $remark, $remarks, $since, $now, $limits) {
            $this->deleteBatchLogs($db, $removingIp, $removingDevice, $appId, $channel, $version, $since);
            $deleted = $this->deleteBatch($db, $appId, $channel, $version, $remarks);
            $this->info("已删除白名单 {$deleted} 条");
            $this->insertRows($db, 'ip', $newIp, $appId, $channel, $version, $platform, $type, $remark, $now);
            $this->insertRows($db, 'device', $newDevice, $appId, $channel, $version, $platform, $type, $remark, $now);
            $this->backfillLogs($db, $appId, $channel, $version, $platform, $type, $remark, $since, $limits);
        });

        UserWhitelistService::cacheForIp();
        UserWhitelistService::cacheForDevice();
        $this->info('完成，缓存已刷新');

        return self::SUCCESS;
    }

    private function deleteOnly(Connection $db, int $appId, string $channel, string $version, string $since, array $remarks, array $removing, bool $dryRun): int
    {
        $removingIp = $this->removingContents($db, 'ip', $appId, $channel, $version, $remarks);
        $removingDevice = $this->removingContents($db, 'device', $appId, $channel, $version, $remarks);
        $logCount = $this->logCount($db, $removingIp, 'ip', $appId, $channel, $version, $since)
            + $this->logCount($db, $removingDevice, 'device', $appId, $channel, $version, $since);
        $this->info("将删除回填访问日志 {$logCount} 条");

        if ($dryRun) {
            $this->info('预览结束，未删除');

            return self::SUCCESS;
        }

        if ($removing === []) {
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('确认只删除这些批次？不会重新写入')) {
            $this->info('已取消');

            return self::SUCCESS;
        }

        $db->transaction(function () use ($db, $removingIp, $removingDevice, $appId, $channel, $version, $since, $remarks) {
            $this->deleteBatchLogs($db, $removingIp, $removingDevice, $appId, $channel, $version, $since);
            $deleted = $this->deleteBatch($db, $appId, $channel, $version, $remarks);
            $this->info("已删除白名单 {$deleted} 条");
        });

        UserWhitelistService::cacheForIp();
        UserWhitelistService::cacheForDevice();
        $this->info('删除完成，缓存已刷新');

        return self::SUCCESS;
    }

    private function logCount(Connection $db, array $contents, string $way, int $appId, string $channel, string $version, string $since): int
    {
        $total = 0;
        foreach (array_chunk(array_keys($contents), 500) as $chunk) {
            $total += $db->table('user_whitelist_log')
                ->where('app_id', $appId)
                ->where('market_channel', $channel)
                ->where('version', $version)
                ->where('source_type', $way)
                ->where('created_at', '>=', $since)
                ->whereIn('source', $chunk)
                ->count();
        }

        return $total;
    }

    private function since(): string
    {
        $since = trim((string) $this->option('since'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            return $since . ' 00:00:00';
        }

        return $since;
    }

    private function platform(string $channel): string
    {
        return match ($channel) {
            'ios' => 'ios',
            'hongmeng' => 'hongmeng',
            default => 'android',
        };
    }

    private function rowsToRemove(Connection $db, int $appId, string $channel, string $version, array $remarks): array
    {
        return $db->table('user_whitelist')
            ->selectRaw('way, count(*) as c, min(created_at) as min_at, max(created_at) as max_at')
            ->where('app_id', $appId)
            ->where('market_channel', $channel)
            ->where('version', $version)
            ->where('source', 1)
            ->whereIn('way', ['ip', 'device'])
            ->whereIn('remark', $remarks)
            ->groupBy('way')
            ->get()
            ->all();
    }

    private function removingContents(Connection $db, string $way, int $appId, string $channel, string $version, array $remarks): array
    {
        return $db->table('user_whitelist')
            ->where('way', $way)
            ->where('app_id', $appId)
            ->where('market_channel', $channel)
            ->where('version', $version)
            ->where('source', 1)
            ->whereIn('remark', $remarks)
            ->pluck('content')
            ->flip()
            ->all();
    }

    private function prepareNewUsers(Connection $db, int $appId, string $channel, string $since): void
    {
        $db->unprepared('SET SESSION group_concat_max_len = 8192');
        $db->unprepared('DROP TEMPORARY TABLE IF EXISTS tmp_wm_new_uuid');
        $db->unprepared("CREATE TEMPORARY TABLE tmp_wm_new_uuid (uuid varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB");

        $channels = SystemApp::marketChannelAliases($channel);
        $placeholders = implode(', ', array_fill(0, count($channels), '?'));
        $db->affectingStatement(
            "INSERT IGNORE INTO tmp_wm_new_uuid (uuid)
             SELECT uuid FROM user_uuids
             WHERE app_id = ? AND market_channel IN ({$placeholders}) AND created_at >= ?",
            array_merge([$appId], $channels, [$since])
        );
    }

    private function collect(Connection $db, int $appId, string $channel, string $version, string $since): array
    {
        [$scope, $bind] = $this->scope($appId, $channel, $version, $since);
        $newUuid = (int) $db->selectOne('SELECT COUNT(*) AS c FROM tmp_wm_new_uuid')->c;
        $ipRows = $db->select(
            "SELECT l.ip AS content,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.device = '' OR CHAR_LENGTH(l.device) > 64, NULL, l.device) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_device,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.region = '', NULL, l.region) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_region
             FROM user_access_log l
             INNER JOIN tmp_wm_new_uuid n ON n.uuid = l.uuid
             WHERE {$scope} AND l.ip <> ''
             GROUP BY l.ip",
            $bind
        );
        $deviceRows = $db->select(
            "SELECT l.device AS content,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.ip = '', NULL, l.ip) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_ip,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(l.region = '', NULL, l.region) ORDER BY l.id DESC SEPARATOR '|#|'), '|#|', 1) AS source_region
             FROM user_access_log l
             INNER JOIN tmp_wm_new_uuid n ON n.uuid = l.uuid
             WHERE {$scope} AND l.device <> '' AND CHAR_LENGTH(l.device) <= 64
             GROUP BY l.device",
            $bind
        );

        return [$ipRows, $deviceRows, $newUuid];
    }

    private function accessCount(Connection $db, int $appId, string $channel, string $version, string $since): int
    {
        [$scope, $bind] = $this->scope($appId, $channel, $version, $since);

        return (int) $db->selectOne(
            "SELECT COUNT(*) AS c
             FROM user_access_log l
             INNER JOIN tmp_wm_new_uuid n ON n.uuid = l.uuid
             WHERE {$scope}",
            $bind
        )->c;
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

    private function deleteBatch(Connection $db, int $appId, string $channel, string $version, array $remarks): int
    {
        return $db->table('user_whitelist')
            ->where('app_id', $appId)
            ->where('market_channel', $channel)
            ->where('version', $version)
            ->where('source', 1)
            ->whereIn('way', ['ip', 'device'])
            ->whereIn('remark', $remarks)
            ->delete();
    }

    private function deleteBatchLogs(Connection $db, array $ips, array $devices, int $appId, string $channel, string $version, string $since): void
    {
        $this->deleteLogs($db, array_keys($ips), 'ip', $appId, $channel, $version, $since);
        $this->deleteLogs($db, array_keys($devices), 'device', $appId, $channel, $version, $since);
    }

    private function deleteLogs(Connection $db, array $contents, string $way, int $appId, string $channel, string $version, string $since): void
    {
        foreach (array_chunk($contents, 500) as $chunk) {
            $db->table('user_whitelist_log')
                ->where('app_id', $appId)
                ->where('market_channel', $channel)
                ->where('version', $version)
                ->where('source_type', $way)
                ->where('created_at', '>=', $since)
                ->whereIn('source', $chunk)
                ->delete();
        }
    }

    private function insertRows(Connection $db, string $way, array $rows, int $appId, string $channel, string $version, string $platform, int $type, string $remark, string $now): void
    {
        $written = 0;
        foreach (array_chunk($rows, 300) as $chunk) {
            $payload = [];
            foreach ($chunk as $row) {
                $payload[] = [
                    'app_id' => $appId,
                    'platform' => $platform,
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

    private function backfillLogs(Connection $db, int $appId, string $channel, string $version, string $platform, int $type, string $remark, string $since, array $limits): void
    {
        [$scope, $bind] = $this->scope($appId, $channel, $version, $since);
        $deviceWritten = $this->insertLogs($db, $scope, $bind, $platform, $type, $remark, $limits, 'device', 'l.device');
        $ipWritten = $this->insertLogs($db, $scope, $bind, $platform, $type, $remark, $limits, 'ip', 'l.ip');
        $this->info("访问日志已回填 设备 {$deviceWritten} 条，IP {$ipWritten} 条");
    }

    private function insertLogs(Connection $db, string $scope, array $bind, string $platform, int $type, string $remark, array $limits, string $way, string $sourceColumn): int
    {
        $sourceLimit = (int) $limits['source'];

        return $db->affectingStatement(
            "INSERT INTO user_whitelist_log
                (app_id, user_id, uuid, platform, market_channel, version, device, ip, region, type, source, source_type, created_at)
             SELECT
                l.app_id,
                IFNULL(l.user_id, 0),
                LEFT(l.uuid, {$limits['uuid']}),
                ?,
                LEFT(l.market_channel, {$limits['market_channel']}),
                LEFT(l.version, {$limits['version']}),
                LEFT(l.device, {$limits['device']}),
                LEFT(l.ip, {$limits['ip']}),
                LEFT(IFNULL(l.region, ''), {$limits['region']}),
                ?,
                LEFT({$sourceColumn}, {$sourceLimit}),
                ?,
                l.created_at
             FROM user_access_log l
             INNER JOIN tmp_wm_new_uuid n ON n.uuid = l.uuid
             INNER JOIN user_whitelist w
                ON w.content = {$sourceColumn}
               AND w.way = ?
               AND w.app_id = l.app_id
               AND w.market_channel = l.market_channel
               AND w.version = l.version
               AND w.remark = ?
             WHERE {$scope} AND {$sourceColumn} <> '' AND CHAR_LENGTH({$sourceColumn}) <= {$sourceLimit}
             ORDER BY l.id",
            array_merge([$platform, $type, $way, $way, $remark], $bind)
        );
    }

    private function logLimits(Connection $db): array
    {
        $fallback = [
            'uuid' => 32,
            'device' => 32,
            'ip' => 32,
            'region' => 32,
            'source' => 32,
            'market_channel' => 32,
            'version' => 32,
        ];
        $limits = [];
        foreach ($fallback as $column => $length) {
            $limits[$column] = $this->columnLimit($db, 'user_whitelist_log', $column, $length);
        }

        return $limits;
    }

    private function columnLimit(Connection $db, string $table, string $column, int $fallback): int
    {
        $row = $db->selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $length = (int) ($row->len ?? 0);

        return $length > 0 ? $length : $fallback;
    }

    private function scope(int $appId, string $channel, string $version, string $since): array
    {
        return [
            "l.app_id = ? AND l.market_channel = ? AND l.version = ? AND l.created_at >= ? AND l.uuid <> ''",
            [$appId, $channel, $version, $since],
        ];
    }
}
