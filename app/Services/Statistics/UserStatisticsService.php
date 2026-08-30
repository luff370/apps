<?php

namespace App\Services\Statistics;

use App\Models\SystemApp;
use App\Models\User;
use App\Models\UserStatistic;
use App\Models\UserUuid;
use App\Services\Service;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Redis;

class UserStatisticsService extends Service
{
    private mixed $redisClient;

    private const STAT_CACHE_TTL = 259200;

    /**
     * 用户活跃统计使用 Redis Set 暂存当天活跃设备/用户标识。
     *
     * Set 天然去重，适合“同一个用户当天多次访问只算一次活跃”的口径；后续定时任务可以把
     * Set 的基数汇总到 user_statistics 日表里，供首页和报表页快速查询。
     */
    public function __construct()
    {
        $this->redisClient = Redis::connection()->client();
    }

    /**
     * 生成指定应用、指定日期、指定渠道的活跃用户 Redis key。
     *
     * 空渠道表示应用合计，key 格式与历史数据保持一致，避免升级当天 Redis 里已有集合失效。
     */
    public function getUserActiveStatKey($appId, $date = null, $marketChannel = ''): string
    {
        $date = $date ?? today()->toDateString();
        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        if ($marketChannel === '') {
            return sprintf('user_active_stat:%s-%s', $appId, $date);
        }

        return sprintf('user_active_stat:%s:%s-%s', $appId, $marketChannel, $date);
    }

    /**
     * 当天出现过的应用市场集合，供定时任务按渠道落库。
     */
    public function getUserActiveChannelIndexKey($appId, $date = null): string
    {
        return sprintf('user_active_stat_channels:%s-%s', $appId, $date ?? today()->toDateString());
    }

    public function getNewUuidStatKey($appId, $date = null, $marketChannel = ''): string
    {
        $date = $date ?? today()->toDateString();
        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        if ($marketChannel === '') {
            return sprintf('user_new_uuid:%s-%s', $appId, $date);
        }

        return sprintf('user_new_uuid:%s:%s-%s', $appId, $marketChannel, $date);
    }

    public function getNewUuidChannelIndexKey($appId, $date = null): string
    {
        return sprintf('user_new_uuid_channels:%s-%s', $appId, $date ?? today()->toDateString());
    }

    /**
     * 记录一次用户活跃。
     *
     * 同时写入应用合计 Set 和渠道 Set，同一 uuid 在同一天多次访问仍只算 1 个活跃。
     */
    public function userActiveStat($uuid, $appId, $marketChannel = ''): void
    {
        $this->addToStatSet($this->getUserActiveStatKey($appId), $uuid);

        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        if ($marketChannel === '') {
            return;
        }

        $this->addToStatSet($this->getUserActiveStatKey($appId, null, $marketChannel), $uuid);
        $this->addToStatSet($this->getUserActiveChannelIndexKey($appId), $marketChannel);
    }

    /**
     * app_info 入口：记当天活跃，并把首次出现的 uuid 记为新增用户。
     */
    public function recordAppInfoVisit($uuid, $appId, $marketChannel = ''): void
    {
        $uuid = trim((string)$uuid);
        $appId = (int)$appId;
        if ($uuid === '' || $appId <= 0) {
            return;
        }

        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        $this->userActiveStat($uuid, $appId, $marketChannel);

        $inserted = UserUuid::query()->insertOrIgnore([
            'app_id' => $appId,
            'uuid' => $uuid,
            'market_channel' => $marketChannel,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (!$inserted) {
            return;
        }

        $this->addToStatSet($this->getNewUuidStatKey($appId), $uuid);
        if ($marketChannel === '') {
            return;
        }

        $this->addToStatSet($this->getNewUuidStatKey($appId, null, $marketChannel), $uuid);
        $this->addToStatSet($this->getNewUuidChannelIndexKey($appId), $marketChannel);
    }

    /**
     * 获取当前应用当天实时活跃人数。
     *
     * 空渠道返回应用合计；指定渠道返回该应用市场的去重人数。
     */
    public function getActiveUserCount($appId, $marketChannel = ''): int
    {
        return (int)$this->redisClient->sCard($this->getUserActiveStatKey($appId, null, $marketChannel));
    }

    public function getNewUuidCount($appId, $marketChannel = ''): int
    {
        return (int)$this->redisClient->sCard($this->getNewUuidStatKey($appId, null, $marketChannel));
    }

    /**
     * 当天已产生过活跃记录的应用市场列表。
     */
    public function getActiveMarketChannels($appId, $date = null): array
    {
        return $this->channelMembers($this->getUserActiveChannelIndexKey($appId, $date));
    }

    public function getNewUuidMarketChannels($appId, $date = null): array
    {
        return $this->channelMembers($this->getNewUuidChannelIndexKey($appId, $date));
    }

    /**
     * 删除指定日期的活跃/新增 UUID 缓存。
     *
     * 定时任务每天会删掉昨天的 key；写入时也会设 3 天过期，避免漏删后 Redis 里临时 Set 一直堆积。
     */
    public function delUserActiveStatKey($appId, $date = null): void
    {
        $date = $date ?? today()->toDateString();
        $keys = [
            $this->getUserActiveStatKey($appId, $date),
            $this->getUserActiveChannelIndexKey($appId, $date),
            $this->getNewUuidStatKey($appId, $date),
            $this->getNewUuidChannelIndexKey($appId, $date),
        ];
        foreach ($this->getActiveMarketChannels($appId, $date) as $marketChannel) {
            $keys[] = $this->getUserActiveStatKey($appId, $date, $marketChannel);
        }
        foreach ($this->getNewUuidMarketChannels($appId, $date) as $marketChannel) {
            $keys[] = $this->getNewUuidStatKey($appId, $date, $marketChannel);
        }

        foreach (array_unique($keys) as $key) {
            $this->redisClient->del($key);
        }
    }

    /**
     * 获取用户趋势图数据。
     *
     * 按日期聚合新增和活跃人数；只读 market_channel 为空的应用合计行，避免与分渠道行加总重复。
     * app_id 为 0 时表示全应用汇总，指定 app_id 时用于单应用看板。
     */
    public function userCharts($days, $appId = 0)
    {
        return UserStatistic::query()->when($appId > 0, function ($query) use ($appId) {
            $query->where('app_id', $appId);
        })
            ->where('market_channel', '')
            ->selectRaw('date, sum(new_users_count) as new_users_count, sum(active_users_count) as active_users_count')
            ->orderBy('date', 'desc')
            ->groupBy('date')
            ->limit($days)
            ->get();
    }

    /**
     * 报表管理-用户统计汇总：新增用户=新增 UUID，注册用户=注册账号。
     */
    public function reportBasic(array $filter): array
    {
        [$start, $end, $appId, $marketChannel] = $this->reportFilter($filter);
        $newUsers = $this->newUuidUsers($start, $end, $appId, $marketChannel);
        $registeredUsers = $this->registeredUsers($start, $end, $appId, $marketChannel);

        return [
            'new_users' => $newUsers,
            'registered_users' => $registeredUsers,
            'register_rate' => $this->registerRate($registeredUsers, $newUsers),
        ];
    }

    /**
     * 报表管理-用户统计趋势，按天补齐新增、注册和注册率。
     */
    public function reportTrend(array $filter): array
    {
        [$start, $end, $appId, $marketChannel] = $this->reportFilter($filter);
        $newRows = $this->newUuidUsersByDate($start, $end, $appId, $marketChannel);
        $registeredRows = $this->registeredUsersByDate($start, $end, $appId, $marketChannel);

        $xAxis = [];
        $newValues = [];
        $registeredValues = [];
        $rateValues = [];
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $date) {
            $key = $date->format('Y-m-d');
            $newUsers = (int)($newRows[$key] ?? 0);
            $registeredUsers = (int)($registeredRows[$key] ?? 0);
            $xAxis[] = $date->format('m-d');
            $newValues[] = $newUsers;
            $registeredValues[] = $registeredUsers;
            $rateValues[] = $this->registerRate($registeredUsers, $newUsers);
        }

        return [
            'xAxis' => $xAxis,
            'series' => [
                ['name' => '新增用户', 'data' => $newValues],
                ['name' => '注册用户', 'data' => $registeredValues],
                ['name' => '注册率', 'data' => $rateValues],
            ],
        ];
    }

    private function reportFilter(array $filter): array
    {
        $start = Carbon::today();
        $end = Carbon::today();
        $data = trim((string)($filter['data'] ?? ''));
        if ($data !== '' && preg_match('#(\d{4}[/-]\d{1,2}[/-]\d{1,2}).*?(\d{4}[/-]\d{1,2}[/-]\d{1,2})#', $data, $matches)) {
            $start = Carbon::parse(str_replace('/', '-', $matches[1]));
            $end = Carbon::parse(str_replace('/', '-', $matches[2]));
        } else {
            if (!empty($filter['start_date'])) {
                $start = Carbon::parse($filter['start_date']);
            }
            if (!empty($filter['end_date'])) {
                $end = Carbon::parse($filter['end_date']);
            }
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $marketChannel = strtolower(trim((string)($filter['market_channel'] ?? '')));
        if ($marketChannel === 'all') {
            $marketChannel = '';
        }

        return [$start->startOfDay(), $end->endOfDay(), (int)($filter['app_id'] ?? 0), $marketChannel];
    }

    private function newUuidUsers(Carbon $start, Carbon $end, int $appId, string $marketChannel): int
    {
        return (int)$this->newUuidQuery($start, $end, $appId, $marketChannel)->count();
    }

    private function newUuidUsersByDate(Carbon $start, Carbon $end, int $appId, string $marketChannel): array
    {
        return $this->newUuidQuery($start, $end, $appId, $marketChannel)
            ->selectRaw('DATE(created_at) as date_value, COUNT(*) as value')
            ->groupBy('date_value')
            ->pluck('value', 'date_value')
            ->toArray();
    }

    private function newUuidQuery(Carbon $start, Carbon $end, int $appId, string $marketChannel)
    {
        return UserUuid::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($appId > 0, fn ($query) => $query->where('app_id', $appId))
            ->when(
                $marketChannel !== '',
                fn ($query) => $query->whereIn('market_channel', SystemApp::marketChannelAliases($marketChannel))
            );
    }

    private function registeredUsers(Carbon $start, Carbon $end, int $appId, string $marketChannel): int
    {
        $stat = UserStatistic::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->tap(fn ($query) => $this->applyUserStatisticChannel($query, $appId, $marketChannel))
            ->sum('new_users_count');

        if ($stat > 0) {
            return (int)$stat;
        }

        return (int)$this->registeredUserQuery($start, $end, $appId, $marketChannel)->count();
    }

    private function registeredUsersByDate(Carbon $start, Carbon $end, int $appId, string $marketChannel): array
    {
        $rows = UserStatistic::query()
            ->selectRaw('date, SUM(new_users_count) as value')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->tap(fn ($query) => $this->applyUserStatisticChannel($query, $appId, $marketChannel))
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date->format('Y-m-d') => (int)$row->value])
            ->toArray();

        if ($rows) {
            return $rows;
        }

        return $this->registeredUserQuery($start, $end, $appId, $marketChannel)
            ->selectRaw('FROM_UNIXTIME(reg_time, "%Y-%m-%d") as date_value, COUNT(*) as value')
            ->groupBy('date_value')
            ->pluck('value', 'date_value')
            ->toArray();
    }

    private function registeredUserQuery(Carbon $start, Carbon $end, int $appId, string $marketChannel)
    {
        return User::query()
            ->whereBetween('reg_time', [$start->timestamp, $end->timestamp])
            ->when($appId > 0, fn ($query) => $query->where('app_id', $appId))
            ->when(
                $marketChannel !== '',
                fn ($query) => $query->whereIn('market_channel', SystemApp::marketChannelAliases($marketChannel))
            );
    }

    private function applyUserStatisticChannel($query, int $appId, string $marketChannel)
    {
        return $query
            ->when($appId > 0, fn ($query) => $query->where('app_id', $appId))
            ->when(
                $marketChannel !== '',
                fn ($query) => $query->whereIn('market_channel', SystemApp::marketChannelAliases($marketChannel)),
                fn ($query) => $query->where('market_channel', '')
            );
    }

    private function registerRate(int $registeredUsers, int $newUsers): float
    {
        if ($newUsers <= 0) {
            return 0;
        }

        return round($registeredUsers / $newUsers * 100, 2);
    }

    private function addToStatSet(string $key, $member): void
    {
        $this->redisClient->sAdd($key, $member);
        $this->redisClient->expire($key, self::STAT_CACHE_TTL);
    }

    private function channelMembers(string $key): array
    {
        $members = $this->redisClient->sMembers($key) ?: [];

        return array_values(array_unique(array_filter(array_map([$this, 'normalizeMarketChannel'], $members))));
    }

    private function normalizeMarketChannel($marketChannel): string
    {
        return strtolower(trim((string)$marketChannel));
    }

}
