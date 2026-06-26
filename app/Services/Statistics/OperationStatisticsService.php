<?php

namespace App\Services\Statistics;

use App\Models\AdAccessLog;
use App\Models\AppAdRevenueDaily;
use App\Models\MemberOrder;
use App\Models\SubscriptionOrder;
use App\Models\SystemApp;
use App\Models\User;
use App\Models\UserStatistic;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationStatisticsService
{
    private const MONEY_SCALE = 2;

    public function dashboard(array $filter): array
    {
        $trendRange = $filter['trend_range'] ?? '30day';
        $appId = (int)($filter['app_id'] ?? 0);
        $today = Carbon::today();

        return [
            'updated_at' => now()->format('Y-m-d H:i'),
            'summary' => [
                'today' => $this->summaryPeriod($today->copy(), $today->copy(), $today->copy()->subDay(), $today->copy()->subDay(), $appId),
                'week' => $this->summaryPeriod($today->copy()->startOfWeek(), $today->copy()->endOfWeek(), $today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek(), $appId),
                'month' => $this->summaryPeriod($today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth(), $appId),
            ],
            'rankings' => [
                'today_recharge' => $this->rank('recharge', $today->copy(), $today->copy(), $today->copy()->subDay(), $today->copy()->subDay(), $appId),
                'today_ad' => $this->rank('ad', $today->copy(), $today->copy(), $today->copy()->subDay(), $today->copy()->subDay(), $appId),
                'week_recharge' => $this->rank('recharge', $today->copy()->startOfWeek(), $today->copy()->endOfWeek(), $today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek(), $appId),
                'week_ad' => $this->rank('ad', $today->copy()->startOfWeek(), $today->copy()->endOfWeek(), $today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek(), $appId),
            ],
            'trends' => [
                'new_users' => $this->dashboardTrend('new_users', $trendRange, $appId),
                'recharge_revenue' => $this->dashboardTrend('recharge_revenue', $trendRange, $appId),
                'ad_revenue' => $this->dashboardTrend('ad_revenue', $trendRange, $appId),
            ],
        ];
    }

    public function rechargeSummary(array $filter): array
    {
        [$start, $end] = $this->dateRange($filter, true);
        $appId = (int)($filter['app_id'] ?? 0);
        $orderQuery = $this->memberOrders($start, $end, $appId);
        $paidOrderQuery = $this->paidMemberOrders($start, $end, $appId);
        $activeUsers = $this->activeUsers($start, $end, $appId);
        $newUsers = $this->newUsers($start, $end, $appId);

        $orderUsers = (clone $orderQuery)->distinct('user_id')->count('user_id');
        $orderCount = (clone $orderQuery)->count();
        $orderAmount = $this->money((clone $orderQuery)->sum('member_price'));
        if ($orderAmount <= 0) {
            $orderAmount = $this->money((clone $orderQuery)->sum('pay_price'));
        }

        $paidUsers = (clone $paidOrderQuery)->distinct('user_id')->count('user_id');
        $paidCount = (clone $paidOrderQuery)->count();
        $paidAmount = $this->money((clone $paidOrderQuery)->sum('pay_price'));

        $trialUsers = $this->trialUsers($start, $end, $appId);
        $renew = $this->renewStats($start, $end, $appId);
        $cancel = $this->cancelStats($start, $end, $appId);

        return [
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'active_index' => $this->rate($activeUsers, max($newUsers, 1), false),
            'order_users' => $orderUsers,
            'order_count' => $orderCount,
            'order_amount' => $orderAmount,
            'order_rate' => $this->rate($orderUsers, $activeUsers),
            'order_conversion_rate' => $this->rate($orderUsers, $activeUsers),
            'paid_users' => $paidUsers,
            'paid_count' => $paidCount,
            'paid_amount' => $paidAmount,
            'pay_rate' => $this->rate($paidUsers, $activeUsers),
            'pay_conversion_rate' => $this->rate($paidUsers, $orderUsers),
            'trial_users' => $trialUsers,
            'trial_rate' => $this->rate($trialUsers, $activeUsers),
            'renew_users' => $renew['users'],
            'renew_amount' => $renew['amount'],
            'renew_rate' => $this->rate($renew['users'], $trialUsers),
            'cancel_users' => $cancel['users'],
            'cancel_amount' => $cancel['amount'],
            'cancel_rate' => $this->rate($cancel['users'], $trialUsers),
            'renew_cancel_status' => $end->diffInDays(Carbon::today(), false) < 5 ? 'pending' : 'completed',
        ];
    }

    public function rechargeTrend(array $filter): array
    {
        [$start, $end] = $this->dateRange($filter, true);
        $metric = $filter['metric'] ?? 'paid_amount';
        $appId = (int)($filter['app_id'] ?? 0);

        if ($start->isSameDay($end)) {
            $items = [];
            $rows = $this->paidMemberOrders($start, $end, $appId)
                ->selectRaw('HOUR(FROM_UNIXTIME(pay_time)) as hour_value')
                ->selectRaw('COUNT(*) as paid_count')
                ->selectRaw('COUNT(DISTINCT user_id) as paid_users')
                ->selectRaw('COALESCE(SUM(pay_price), 0) as paid_amount')
                ->where('pay_time', '>', 0)
                ->groupBy('hour_value')
                ->get()
                ->keyBy('hour_value');

            for ($hour = 0; $hour < 24; $hour++) {
                $row = $rows->get($hour);
                $items[] = [
                    'label' => sprintf('%02d:00', $hour),
                    'value' => $this->metricValue($row, $metric),
                ];
            }

            return ['granularity' => 'hour', 'items' => $items];
        }

        $rows = $this->paidMemberOrders($start, $end, $appId)
            ->selectRaw('DATE(FROM_UNIXTIME(pay_time)) as date_value')
            ->selectRaw('COUNT(*) as paid_count')
            ->selectRaw('COUNT(DISTINCT user_id) as paid_users')
            ->selectRaw('COALESCE(SUM(pay_price), 0) as paid_amount')
            ->where('pay_time', '>', 0)
            ->groupBy('date_value')
            ->get()
            ->keyBy('date_value');

        $items = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->format('Y-m-d');
            $items[] = [
                'label' => $date->format('m-d'),
                'value' => $this->metricValue($rows->get($key), $metric),
            ];
        }

        return ['granularity' => 'day', 'items' => $items];
    }

    public function revenueReport(array $filter): array
    {
        [$start, $end] = $this->dateRange($filter);
        $appKeyword = trim((string)($filter['app_keyword'] ?? ''));
        $appIds = $this->filteredAppIds($appKeyword);

        if ($appKeyword !== '' && empty($appIds)) {
            return [
                'list' => [],
                'count' => 0,
                'summary' => $this->emptyRevenueSummary(),
                'platform_summary' => [],
            ];
        }

        $apps = $this->apps($appIds);
        $userStats = $this->userStatsRows($start, $end, $appIds);
        $rechargeStats = $this->rechargeRows($start, $end, $appIds);
        $adStats = $this->adRows($start, $end, $appIds);
        $accessStats = $this->adAccessRows($start, $end, $appIds);
        $rows = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dateText = $date->format('Y-m-d');
            foreach ($apps as $app) {
                $key = $dateText . '_' . $app['id'];
                $user = $userStats[$key] ?? ['new_users' => 0, 'active_users' => 0];
                $rechargeRevenue = $this->money($rechargeStats[$key]['recharge_revenue'] ?? 0);
                $platforms = $this->buildPlatforms($adStats[$key] ?? [], $accessStats[$key] ?? []);
                $adSlots = $this->buildAdSlots($adStats[$key] ?? [], $accessStats[$key] ?? [], $app, $dateText);
                $adRevenue = $this->money(array_sum(array_column($platforms, 'ad_revenue')));
                $requestCount = (int)array_sum(array_column($platforms, 'request_count'));
                $successCount = (int)array_sum(array_column($platforms, 'success_count'));
                $showCount = (int)array_sum(array_column($platforms, 'show_count'));
                $clickCount = (int)array_sum(array_column($platforms, 'click_count'));
                $activeUsers = (int)$user['active_users'];
                $totalRevenue = $this->money($adRevenue + $rechargeRevenue);

                $rows[] = [
                    'date' => $dateText,
                    'app_id' => (int)$app['id'],
                    'app_name' => $app['name'],
                    'package_name' => $app['package_name'],
                    'new_users' => (int)$user['new_users'],
                    'umeng_new_users' => (int)$user['new_users'],
                    'active_users' => $activeUsers,
                    'ad_revenue' => $adRevenue,
                    'recharge_revenue' => $rechargeRevenue,
                    'total_revenue' => $totalRevenue,
                    'app_arpu' => $this->money($activeUsers ? $totalRevenue / $activeUsers : 0),
                    'ad_arpu' => $this->money($activeUsers ? $adRevenue / $activeUsers : 0),
                    'recharge_arpu' => $this->money($activeUsers ? $rechargeRevenue / $activeUsers : 0),
                    'ecpm' => $showCount ? $this->number($adRevenue / $showCount * 1000) : 0,
                    'request_count' => $requestCount,
                    'success_count' => $successCount,
                    'show_count' => $showCount,
                    'click_count' => $clickCount,
                    'request_success_rate' => $this->rate($successCount, $requestCount),
                    'show_rate' => $this->rate($showCount, $successCount),
                    'click_rate' => $this->rate($clickCount, $showCount),
                    'data_status' => $this->mergeStatus($platforms),
                    'updated_at' => $this->latestCollectedAt($adStats[$key] ?? []),
                    'platforms' => $platforms,
                    'ad_slots' => $adSlots,
                    'collect_logs' => $this->collectLogs($platforms, $dateText),
                ];
            }
        }

        $rows = $this->sortRevenueRows($rows, $filter);
        $count = count($rows);
        $page = max(1, (int)($filter['page'] ?? 1));
        $limit = max(1, (int)($filter['limit'] ?? 20));

        return [
            'list' => array_slice($rows, ($page - 1) * $limit, $limit),
            'count' => $count,
            'summary' => $this->revenueSummary($rows),
            'platform_summary' => $this->platformSummary($rows),
        ];
    }

    public function revenueDetail(string $date, int $appId): array
    {
        return $this->revenueReport([
            'start_date' => $date,
            'end_date' => $date,
            'app_keyword' => (string)$appId,
            'page' => 1,
            'limit' => 1,
        ])['list'][0] ?? [];
    }

    public function markRecollect(array $data): array
    {
        $date = $this->parseDate($data['date'] ?? null, Carbon::yesterday());
        $appId = (int)($data['app_id'] ?? 0);

        AppAdRevenueDaily::query()
            ->whereDate('date', $date)
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->update([
                'data_status' => AppAdRevenueDaily::STATUS_COLLECTING,
                'collect_message' => '已标记重新采集',
                'collected_at' => now(),
            ]);

        return ['date' => $date->format('Y-m-d'), 'app_id' => $appId, 'status' => AppAdRevenueDaily::STATUS_COLLECTING];
    }

    private function summaryPeriod(Carbon $start, Carbon $end, Carbon $compareStart, Carbon $compareEnd, int $appId): array
    {
        $current = $this->periodMetrics($start, $end, $appId);
        $compare = $this->periodMetrics($compareStart, $compareEnd, $appId);

        return [
            'new_users' => $this->metric($current['new_users'], $compare['new_users']),
            'active_users' => $this->metric($current['active_users'], $compare['active_users']),
            'recharge_revenue' => $this->metric($current['recharge_revenue'], $compare['recharge_revenue']),
            'ad_revenue' => $this->metric($current['ad_revenue'], $compare['ad_revenue']),
        ];
    }

    private function periodMetrics(Carbon $start, Carbon $end, int $appId): array
    {
        return [
            'new_users' => $this->newUsers($start, $end, $appId),
            'active_users' => $this->activeUsers($start, $end, $appId),
            'recharge_revenue' => $this->money($this->paidMemberOrders($start, $end, $appId)->sum('pay_price')),
            'ad_revenue' => $this->money($this->adRevenueQuery($start, $end, $appId)->sum('ad_revenue')),
        ];
    }

    private function metric(float|int $value, float|int $compareValue): array
    {
        $growthValue = $this->number($value - $compareValue);

        return [
            'value' => is_float($value) ? $this->money($value) : (int)$value,
            'compare_value' => is_float($compareValue) ? $this->money($compareValue) : (int)$compareValue,
            'growth_value' => $growthValue,
            'growth_rate' => $compareValue ? $this->rate($growthValue, $compareValue) : '',
        ];
    }

    private function rank(string $type, Carbon $start, Carbon $end, Carbon $compareStart, Carbon $compareEnd, int $appId): array
    {
        $apps = $this->apps($appId > 0 ? [$appId] : []);
        $current = $type === 'ad'
            ? $this->adRevenueByApp($start, $end, $appId)
            : $this->rechargeByApp($start, $end, $appId);
        $compare = $type === 'ad'
            ? $this->adRevenueByApp($compareStart, $compareEnd, $appId)
            : $this->rechargeByApp($compareStart, $compareEnd, $appId);
        $total = max(array_sum($current), 0.01);
        $list = [];

        foreach ($current as $id => $value) {
            if ($value <= 0) {
                continue;
            }
            $app = $apps[$id] ?? ['id' => $id, 'name' => '', 'package_name' => ''];
            $compareValue = (float)($compare[$id] ?? 0);
            $list[] = [
                'rank' => 0,
                'app_id' => (int)$id,
                'app_name' => $app['name'],
                'package_name' => $app['package_name'],
                'value' => $this->money($value),
                'compare_value' => $this->money($compareValue),
                'growth_rate' => $compareValue ? $this->rate($value - $compareValue, $compareValue) : '',
                'ratio' => $this->rate($value, $total),
            ];
        }

        usort($list, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        }, array_slice($list, 0, 10), array_keys(array_slice($list, 0, 10)));
    }

    private function dashboardTrend(string $metric, string $range, int $appId): array
    {
        if ($range === '12month') {
            return $this->monthlyTrend($metric, $appId);
        }

        $days = match ($range) {
            '7day' => 7,
            'month' => Carbon::today()->day,
            default => 30,
        };
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);
        $compareEnd = $start->copy()->subDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1);
        $current = $this->dailyMetricRows($metric, $start, $end, $appId);
        $compare = $this->dailyMetricRows($metric, $compareStart, $compareEnd, $appId);
        $items = [];

        foreach (CarbonPeriod::create($start, $end) as $index => $date) {
            $key = $date->format('Y-m-d');
            $compareKey = $compareStart->copy()->addDays($index)->format('Y-m-d');
            $value = (float)($current[$key] ?? 0);
            $compareValue = (float)($compare[$compareKey] ?? 0);
            $items[] = [
                'date' => $key,
                'value' => $metric === 'new_users' ? (int)$value : $this->money($value),
                'compare_value' => $metric === 'new_users' ? (int)$compareValue : $this->money($compareValue),
                'growth_rate' => $compareValue ? $this->rate($value - $compareValue, $compareValue) : '',
            ];
        }

        return $items;
    }

    private function monthlyTrend(string $metric, int $appId): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(11);
        $end = Carbon::today()->endOfMonth();
        $rows = $this->monthlyMetricRows($metric, $start, $end, $appId);
        $items = [];

        for ($i = 0; $i < 12; $i++) {
            $date = $start->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $value = (float)($rows[$key] ?? 0);
            $items[] = [
                'date' => $key,
                'value' => $metric === 'new_users' ? (int)$value : $this->money($value),
                'compare_value' => '',
                'growth_rate' => '',
            ];
        }

        return $items;
    }

    private function dailyMetricRows(string $metric, Carbon $start, Carbon $end, int $appId): array
    {
        if ($metric === 'new_users') {
            return UserStatistic::query()
                ->selectRaw('date, SUM(new_users_count) as value')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
                ->groupBy('date')
                ->pluck('value', 'date')
                ->toArray();
        }

        if ($metric === 'ad_revenue') {
            return AppAdRevenueDaily::query()
                ->selectRaw('date, SUM(ad_revenue) as value')
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
                ->groupBy('date')
                ->pluck('value', 'date')
                ->toArray();
        }

        return $this->paidMemberOrders($start, $end, $appId)
            ->selectRaw('DATE(FROM_UNIXTIME(pay_time)) as date_value, SUM(pay_price) as value')
            ->where('pay_time', '>', 0)
            ->groupBy('date_value')
            ->pluck('value', 'date_value')
            ->toArray();
    }

    private function monthlyMetricRows(string $metric, Carbon $start, Carbon $end, int $appId): array
    {
        if ($metric === 'new_users') {
            return UserStatistic::query()
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month_value, SUM(new_users_count) as value")
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
                ->groupBy('month_value')
                ->pluck('value', 'month_value')
                ->toArray();
        }

        if ($metric === 'ad_revenue') {
            return AppAdRevenueDaily::query()
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month_value, SUM(ad_revenue) as value")
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
                ->groupBy('month_value')
                ->pluck('value', 'month_value')
                ->toArray();
        }

        return $this->paidMemberOrders($start, $end, $appId)
            ->selectRaw("DATE_FORMAT(FROM_UNIXTIME(pay_time), '%Y-%m') as month_value, SUM(pay_price) as value")
            ->where('pay_time', '>', 0)
            ->groupBy('month_value')
            ->pluck('value', 'month_value')
            ->toArray();
    }

    private function userStatsRows(Carbon $start, Carbon $end, array $appIds): array
    {
        return UserStatistic::query()
            ->selectRaw('date, app_id, SUM(new_users_count) as new_users, SUM(active_users_count) as active_users')
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($appIds, fn (Builder $query) => $query->whereIn('app_id', $appIds))
            ->groupBy('date', 'app_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date->format('Y-m-d') . '_' . $row->app_id => [
                'new_users' => (int)$row->new_users,
                'active_users' => (int)$row->active_users,
            ]])
            ->toArray();
    }

    private function rechargeRows(Carbon $start, Carbon $end, array $appIds): array
    {
        return $this->paidMemberOrders($start, $end, 0)
            ->selectRaw('DATE(FROM_UNIXTIME(pay_time)) as date_value, app_id, SUM(pay_price) as recharge_revenue')
            ->where('pay_time', '>', 0)
            ->when($appIds, fn (Builder $query) => $query->whereIn('app_id', $appIds))
            ->groupBy('date_value', 'app_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->date_value . '_' . $row->app_id => [
                'recharge_revenue' => $this->money($row->recharge_revenue),
            ]])
            ->toArray();
    }

    private function adRows(Carbon $start, Carbon $end, array $appIds): array
    {
        $rows = AppAdRevenueDaily::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($appIds, fn (Builder $query) => $query->whereIn('app_id', $appIds))
            ->get();

        return $rows->groupBy(fn ($row) => $row->date->format('Y-m-d') . '_' . $row->app_id)->toArray();
    }

    private function adAccessRows(Carbon $start, Carbon $end, array $appIds): array
    {
        $rows = AdAccessLog::query()
            ->selectRaw('DATE(created_at) as date_value, app_id, ad_channel as platform, ad_code as slot_id, ad_type')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success_count', [AdAccessLog::STATUS_SUCCESS])
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($appIds, fn (Builder $query) => $query->whereIn('app_id', $appIds))
            ->groupBy('date_value', 'app_id', 'platform', 'slot_id', 'ad_type')
            ->get();

        return $rows->groupBy(fn ($row) => $row->date_value . '_' . $row->app_id)->toArray();
    }

    private function buildPlatforms(array $adRows, array $accessRows): array
    {
        $platforms = [];

        foreach ($adRows as $row) {
            $platform = $row['platform'] ?: 'unknown';
            $platforms[$platform] ??= $this->emptyPlatform($platform, $row['platform_name'] ?? '');
            $platforms[$platform]['platform_name'] = $row['platform_name'] ?: $platforms[$platform]['platform_name'];
            $platforms[$platform]['ad_revenue'] += (float)$row['ad_revenue'];
            $platforms[$platform]['request_count'] += (int)$row['request_count'];
            $platforms[$platform]['success_count'] += (int)$row['success_count'];
            $platforms[$platform]['show_count'] += (int)$row['show_count'];
            $platforms[$platform]['click_count'] += (int)$row['click_count'];
            $platforms[$platform]['data_status'] = $this->worseStatus($platforms[$platform]['data_status'], $row['data_status']);
        }

        foreach ($accessRows as $row) {
            $platform = $row['platform'] ?: 'unknown';
            $platforms[$platform] ??= $this->emptyPlatform($platform);
            if ((int)$platforms[$platform]['request_count'] === 0 && (int)$platforms[$platform]['success_count'] === 0) {
                $platforms[$platform]['request_count'] += (int)$row['request_count'];
                $platforms[$platform]['success_count'] += (int)$row['success_count'];
            }
        }

        return array_values(array_map(fn ($row) => $this->completeAdMetrics($row), $platforms));
    }

    private function buildAdSlots(array $adRows, array $accessRows, array $app, string $date): array
    {
        $slots = [];

        foreach ($adRows as $row) {
            $key = ($row['platform'] ?: 'unknown') . '_' . ($row['slot_id'] ?: '') . '_' . ($row['ad_type'] ?: '');
            $slots[$key] ??= $this->emptySlot($app, $date, $row['platform'] ?: 'unknown', $row['slot_id'] ?: '', $row['ad_type'] ?: '');
            $slots[$key]['platform_name'] = $row['platform_name'] ?: $slots[$key]['platform_name'];
            $slots[$key]['slot_name'] = $row['slot_name'] ?: $slots[$key]['slot_name'];
            $slots[$key]['request_count'] += (int)$row['request_count'];
            $slots[$key]['success_count'] += (int)$row['success_count'];
            $slots[$key]['show_count'] += (int)$row['show_count'];
            $slots[$key]['click_count'] += (int)$row['click_count'];
            $slots[$key]['ad_revenue'] += (float)$row['ad_revenue'];
        }

        foreach ($accessRows as $row) {
            $key = ($row['platform'] ?: 'unknown') . '_' . ($row['slot_id'] ?: '') . '_' . ($row['ad_type'] ?: '');
            $slots[$key] ??= $this->emptySlot($app, $date, $row['platform'] ?: 'unknown', $row['slot_id'] ?: '', $row['ad_type'] ?: '');
            if ((int)$slots[$key]['request_count'] === 0 && (int)$slots[$key]['success_count'] === 0) {
                $slots[$key]['request_count'] += (int)$row['request_count'];
                $slots[$key]['success_count'] += (int)$row['success_count'];
            }
        }

        return array_values(array_map(fn ($row) => $this->completeAdMetrics($row), $slots));
    }

    private function emptyPlatform(string $platform, string $platformName = ''): array
    {
        return [
            'platform' => $platform,
            'platform_name' => $platformName ?: (AppAdRevenueDaily::platformMap()[$platform] ?? $platform),
            'ad_revenue' => 0,
            'ecpm' => 0,
            'request_count' => 0,
            'success_count' => 0,
            'show_count' => 0,
            'click_count' => 0,
            'request_success_rate' => '',
            'show_rate' => '',
            'click_rate' => '',
            'data_status' => AppAdRevenueDaily::STATUS_COMPLETED,
        ];
    }

    private function emptySlot(array $app, string $date, string $platform, string $slotId, string $adType): array
    {
        return [
            'date' => $date,
            'app_id' => (int)$app['id'],
            'app_name' => $app['name'],
            'slot_name' => $slotId,
            'slot_id' => $slotId,
            'ad_type' => $adType,
            'platform' => $platform,
            'platform_name' => AppAdRevenueDaily::platformMap()[$platform] ?? $platform,
            'request_count' => 0,
            'success_count' => 0,
            'request_success_rate' => '',
            'show_count' => 0,
            'show_rate' => '',
            'click_count' => 0,
            'click_rate' => '',
            'ecpm' => 0,
            'ad_revenue' => 0,
        ];
    }

    private function completeAdMetrics(array $row): array
    {
        $row['ad_revenue'] = $this->money($row['ad_revenue']);
        $row['ecpm'] = (int)$row['show_count'] > 0 ? $this->number($row['ad_revenue'] / $row['show_count'] * 1000) : 0;
        $row['request_success_rate'] = $this->rate((int)$row['success_count'], (int)$row['request_count']);
        $row['show_rate'] = $this->rate((int)$row['show_count'], (int)$row['success_count']);
        $row['click_rate'] = $this->rate((int)$row['click_count'], (int)$row['show_count']);

        return $row;
    }

    private function collectLogs(array $platforms, string $date): array
    {
        return array_map(function ($platform) use ($date) {
            return [
                'created_at' => $date . ' 00:00:00',
                'platform' => $platform['platform'],
                'platform_name' => $platform['platform_name'],
                'status' => $platform['data_status'],
                'status_text' => AppAdRevenueDaily::statusMap()[$platform['data_status']] ?? $platform['data_status'],
                'message' => $platform['data_status'] === AppAdRevenueDaily::STATUS_COMPLETED ? '采集完成' : '请检查采集任务或广告平台配置',
            ];
        }, $platforms);
    }

    private function revenueSummary(array $rows): array
    {
        $summary = $this->emptyRevenueSummary();
        foreach ($rows as $row) {
            $summary['new_users'] += (int)$row['new_users'];
            $summary['active_users'] += (int)$row['active_users'];
            $summary['ad_revenue'] += (float)$row['ad_revenue'];
            $summary['recharge_revenue'] += (float)$row['recharge_revenue'];
        }
        $summary['ad_revenue'] = $this->money($summary['ad_revenue']);
        $summary['recharge_revenue'] = $this->money($summary['recharge_revenue']);
        $summary['total_revenue'] = $this->money($summary['ad_revenue'] + $summary['recharge_revenue']);
        $summary['app_arpu'] = $summary['active_users'] ? $this->money($summary['total_revenue'] / $summary['active_users']) : 0;

        return $summary;
    }

    private function emptyRevenueSummary(): array
    {
        return [
            'new_users' => 0,
            'active_users' => 0,
            'ad_revenue' => 0,
            'recharge_revenue' => 0,
            'total_revenue' => 0,
            'app_arpu' => 0,
        ];
    }

    private function platformSummary(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            foreach ($row['platforms'] as $platform) {
                $key = $platform['platform'];
                $summary[$key] ??= $this->emptyPlatform($key, $platform['platform_name']);
                $summary[$key]['ad_revenue'] += (float)$platform['ad_revenue'];
                $summary[$key]['request_count'] += (int)$platform['request_count'];
                $summary[$key]['success_count'] += (int)$platform['success_count'];
                $summary[$key]['show_count'] += (int)$platform['show_count'];
                $summary[$key]['click_count'] += (int)$platform['click_count'];
                $summary[$key]['data_status'] = $this->worseStatus($summary[$key]['data_status'], $platform['data_status']);
            }
        }

        return array_values(array_map(fn ($row) => $this->completeAdMetrics($row), $summary));
    }

    private function sortRevenueRows(array $rows, array $filter): array
    {
        $field = $filter['sort_field'] ?? 'date';
        $order = ($filter['sort_order'] ?? 'desc') === 'asc' ? 1 : -1;
        $allow = ['date', 'new_users', 'ad_revenue', 'ecpm', 'recharge_revenue', 'app_arpu'];
        if (!in_array($field, $allow, true)) {
            $field = 'date';
        }

        usort($rows, function ($a, $b) use ($field, $order) {
            if ($a[$field] == $b[$field]) {
                return ((int)$a['app_id'] <=> (int)$b['app_id']);
            }

            return ($a[$field] <=> $b[$field]) * $order;
        });

        return $rows;
    }

    private function newUsers(Carbon $start, Carbon $end, int $appId): int
    {
        $stat = UserStatistic::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->sum('new_users_count');

        if ($stat > 0) {
            return (int)$stat;
        }

        return User::query()
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('reg_time', [$start->copy()->startOfDay()->timestamp, $end->copy()->endOfDay()->timestamp])
            ->count();
    }

    private function activeUsers(Carbon $start, Carbon $end, int $appId): int
    {
        $stat = UserStatistic::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->sum('active_users_count');

        if ($stat > 0) {
            return (int)$stat;
        }

        return DB::table('user_access_log')
            ->when($appId > 0, fn ($query) => $query->where('app_id', $appId))
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->distinct('user_id')
            ->count('user_id');
    }

    private function trialUsers(Carbon $start, Carbon $end, int $appId): int
    {
        $member = MemberOrder::query()
            ->where('is_trial_period', 1)
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('purchase_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->distinct('user_id')
            ->count('user_id');

        $subscription = SubscriptionOrder::query()
            ->where('is_trial_period', 1)
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('purchase_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->distinct('user_id')
            ->count('user_id');

        return $member + $subscription;
    }

    private function renewStats(Carbon $start, Carbon $end, int $appId): array
    {
        $query = SubscriptionOrder::query()
            ->where('status', 'active')
            ->where('is_trial_period', 0)
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('renewal_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        return [
            'users' => (clone $query)->distinct('user_id')->count('user_id'),
            'amount' => $this->money((clone $query)->sum('pay_amount')),
        ];
    }

    private function cancelStats(Carbon $start, Carbon $end, int $appId): array
    {
        $query = SubscriptionOrder::query()
            ->whereNotNull('cancellation_date')
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('cancellation_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        return [
            'users' => (clone $query)->distinct('user_id')->count('user_id'),
            'amount' => $this->money((clone $query)->sum('pay_amount')),
        ];
    }

    private function paidMemberOrders(Carbon $start, Carbon $end, int $appId): Builder
    {
        return MemberOrder::query()
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('pay_time', [$start->copy()->startOfDay()->timestamp, $end->copy()->endOfDay()->timestamp])
            ->where(function (Builder $query) {
                $query->where('paid', 1)->orWhere('pay_status', MemberOrder::PAY_STATUS_PAID);
            });
    }

    private function memberOrders(Carbon $start, Carbon $end, int $appId): Builder
    {
        return MemberOrder::query()
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId))
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
    }

    private function adRevenueQuery(Carbon $start, Carbon $end, int $appId): Builder
    {
        return AppAdRevenueDaily::query()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($appId > 0, fn (Builder $query) => $query->where('app_id', $appId));
    }

    private function rechargeByApp(Carbon $start, Carbon $end, int $appId): array
    {
        return $this->paidMemberOrders($start, $end, $appId)
            ->selectRaw('app_id, SUM(pay_price) as value')
            ->groupBy('app_id')
            ->pluck('value', 'app_id')
            ->map(fn ($value) => (float)$value)
            ->toArray();
    }

    private function adRevenueByApp(Carbon $start, Carbon $end, int $appId): array
    {
        return $this->adRevenueQuery($start, $end, $appId)
            ->selectRaw('app_id, SUM(ad_revenue) as value')
            ->groupBy('app_id')
            ->pluck('value', 'app_id')
            ->map(fn ($value) => (float)$value)
            ->toArray();
    }

    private function apps(array $appIds = []): array
    {
        return SystemApp::query()
            ->select(['id', 'name', 'package_name'])
            ->where('is_del', 0)
            ->when($appIds, fn (Builder $query) => $query->whereIn('id', $appIds))
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->map(fn ($app) => [
                'id' => (int)$app->id,
                'name' => (string)$app->name,
                'package_name' => (string)$app->package_name,
            ])
            ->toArray();
    }

    private function filteredAppIds(string $keyword): array
    {
        if ($keyword === '') {
            return [];
        }

        return SystemApp::query()
            ->where('is_del', 0)
            ->where(function (Builder $query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('package_name', 'like', '%' . $keyword . '%');
                if (is_numeric($keyword)) {
                    $query->orWhere('id', (int)$keyword);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->toArray();
    }

    private function latestCollectedAt(array $rows): string
    {
        $latest = collect($rows)->pluck('collected_at')->filter()->sortDesc()->first();

        return $latest ? Carbon::parse($latest)->format('Y-m-d H:i:s') : '';
    }

    private function mergeStatus(array $platforms): string
    {
        return collect($platforms)->reduce(fn ($status, $platform) => $this->worseStatus($status, $platform['data_status'] ?? ''), AppAdRevenueDaily::STATUS_COMPLETED);
    }

    private function worseStatus(string $current, ?string $next): string
    {
        $weight = [
            AppAdRevenueDaily::STATUS_COMPLETED => 1,
            AppAdRevenueDaily::STATUS_COLLECTING => 2,
            AppAdRevenueDaily::STATUS_PARTIAL => 3,
            AppAdRevenueDaily::STATUS_FAILED => 4,
        ];

        return ($weight[$next] ?? 0) > ($weight[$current] ?? 0) ? $next : $current;
    }

    private function metricValue($row, string $metric): int|float
    {
        if (!$row) {
            return 0;
        }

        return $metric === 'paid_amount' ? $this->money($row->{$metric} ?? 0) : (int)($row->{$metric} ?? 0);
    }

    private function dateRange(array $filter, bool $allowToday = false): array
    {
        $default = $allowToday ? Carbon::today() : Carbon::yesterday();
        $start = $this->parseDate($filter['start_date'] ?? null, $default);
        $end = $this->parseDate($filter['end_date'] ?? null, $start);

        if (!$allowToday && $end->greaterThan(Carbon::yesterday())) {
            $end = Carbon::yesterday();
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private function parseDate(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : $default->copy()->startOfDay();
        } catch (\Throwable) {
            return $default->copy()->startOfDay();
        }
    }

    private function rate(float|int $numerator, float|int $denominator, bool $percent = true): float|string
    {
        if (!$denominator) {
            return '';
        }

        $value = $numerator / $denominator;
        if ($percent) {
            $value *= 100;
        }

        return $this->number($value);
    }

    private function money(float|int|string $value): float
    {
        return round((float)$value, self::MONEY_SCALE);
    }

    private function number(float|int|string $value): float
    {
        return round((float)$value, 2);
    }
}
