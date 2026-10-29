<?php

namespace App\Services\Risk;

use App\Models\SystemApp;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\Ledger\ReasonCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskDossierService
{
    public function __construct(private RiskLedgerService $ledger)
    {
    }

    public function list(array $filter, int $page, int $limit): array
    {
        $query = $this->filtered($filter, false);
        $counts = $this->stateCounts($filter);
        $count = (clone $query)->count();
        $rows = $query->orderByDesc('last_activity_at')->orderBy('ip')
            ->forPage($page, $limit)
            ->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->present($row, false);
        }

        return [
            'list' => $list,
            'count' => $count,
            'state_counts' => $counts,
            'options' => $this->options(),
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
            'complete' => $this->ledger->caughtUp(),
        ];
    }

    public function detail(string $id): ?array
    {
        $ip = IpNormalizer::ipFor($id);
        $row = $ip === '' ? null : DB::table('risk_ip_dossiers')->where('ip', $ip)->first();
        if (!$row) {
            return null;
        }
        $presented = $this->present($row, true);
        $presented['scopes'] = DB::table('risk_scopes')->where('ip', $ip)->orderByDesc('id')->get()->map(function ($scope) {
            return [
                'scope_id' => (int) $scope->id,
                'app_id' => (string) $scope->app_id,
                'device_identity' => $scope->device_identity,
                'user_id' => $scope->user_id,
                'state' => $scope->state,
                'decision' => $scope->decision,
                'reason_codes' => $this->ledger->codes($scope->reason_codes),
                'collected_at' => $this->iso($scope->collected_at),
                'released_at' => $scope->released_at ? $this->iso($scope->released_at) : null,
            ];
        })->all();

        return $presented;
    }

    public function relations(string $id, string $kind, int $page, int $limit): array
    {
        $detail = $this->detail($id);
        if (!$detail) {
            return ['list' => [], 'count' => 0];
        }
        $field = match ($kind) {
            'devices' => 'devices',
            'accounts' => 'accounts',
            'tags' => 'tags',
            default => 'apps',
        };
        $all = $detail[$field] ?? [];
        $count = count($all);

        return [
            'list' => array_slice($all, ($page - 1) * $limit, $limit),
            'count' => $count,
            'has_more' => $page * $limit < $count,
        ];
    }

    public function timeline(string $id, array $filter, int $page, int $limit): ?array
    {
        $ip = IpNormalizer::ipFor($id);
        if ($ip === '' || !DB::table('risk_ip_dossiers')->where('ip', $ip)->exists()) {
            return null;
        }
        $query = DB::table('risk_timeline')->where('ip', $ip);
        if (!empty($filter['day'])) {
            $query->where('occurred_at', '>=', $filter['day'] . ' 00:00:00')
                ->where('occurred_at', '<', Carbon::parse($filter['day'])->addDay()->toDateString() . ' 00:00:00');
        }
        if (!empty($filter['app_id'])) {
            $query->where('app_id', $filter['app_id']);
        }
        if (!empty($filter['event_kind'])) {
            $query->where('event_type', $filter['event_kind']);
        }
        if (!empty($filter['reason_code'])) {
            $query->where('reason_codes', 'like', '%"' . str_replace(['%', '_'], '', $filter['reason_code']) . '"%');
        }
        $count = (clone $query)->count();
        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->forPage($page, $limit)->get();
        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->event($row);
        }
        $days = DB::table('risk_timeline')
            ->where('ip', $ip)
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw("SUM(event_type IN ('risk_hit','intake','reintake')) as risk_count")
            ->selectRaw("SUM(event_type IN ('auto_release','release_revoke')) as action_count")
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('day')
            ->get();

        return [
            'list' => $events,
            'count' => $count,
            'days' => $days,
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
        ];
    }

    private function filtered(array $filter, bool $ignoreState)
    {
        $query = DB::table('risk_ip_dossiers');
        $keyword = trim((string) ($filter['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $query->where(function ($inner) use ($like, $keyword) {
                $inner->where('ip', 'like', $like)->orWhereExists(function ($sub) use ($like) {
                    $sub->selectRaw('1')->from('risk_scopes')
                        ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                        ->where(function ($scope) use ($like) {
                            $scope->where('device_identity', 'like', $like)->orWhere('user_id', 'like', $like);
                        });
                });
                $normalized = IpNormalizer::normalize($keyword);
                if ($normalized !== '') {
                    $inner->orWhere('ip', $normalized);
                }
            });
        }
        if (!empty($filter['market_channel'])) {
            $channel = (string) $filter['market_channel'];
            $query->whereExists(function ($sub) use ($channel) {
                $sub->selectRaw('1')->from('risk_scopes')
                    ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                    ->where('market_channel', $channel);
            });
        }
        if (!empty($filter['app_id'])) {
            $appId = (int) $filter['app_id'];
            $query->whereExists(function ($sub) use ($appId) {
                $sub->selectRaw('1')->from('risk_scopes')
                    ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                    ->where('app_id', $appId);
            });
        }
        if (!empty($filter['reason_code'])) {
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $filter['reason_code']));
            if ($code !== '') {
                $query->where('tags', 'like', '%"code":"' . $code . '"%');
            }
        }
        if (!$ignoreState && !empty($filter['state']) && in_array($filter['state'], ['high', 'watch', 'released'], true)) {
            $query->where('state', $filter['state']);
        }

        return $query;
    }

    private function stateCounts(array $filter): array
    {
        $rows = $this->filtered($filter, true)
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');
        $high = (int) ($rows['high'] ?? 0);
        $watch = (int) ($rows['watch'] ?? 0);
        $released = (int) ($rows['released'] ?? 0);

        return [
            'all' => $high + $watch + $released,
            'high' => $high,
            'watch' => $watch,
            'released' => $released,
        ];
    }

    private function options(): array
    {
        $channels = SystemApp::marketChannelsMap();
        $names = SystemApp::idToNameMap();
        $markets = DB::table('risk_scopes')->whereNotNull('market_channel')->where('market_channel', '<>', '')
            ->distinct()->orderBy('market_channel')->pluck('market_channel');
        $apps = DB::table('risk_scopes')->where('app_id', '>', 0)->distinct()->orderBy('app_id')->pluck('app_id');
        $tags = [];
        foreach (ReasonCatalog::LABELS as $code => $label) {
            $tags[] = ['code' => $code, 'name' => $label];
        }

        return [
            'markets' => $markets->map(fn ($value) => ['value' => $value, 'label' => $channels[$value] ?? $value])->values(),
            'apps' => $apps->map(fn ($id) => ['id' => (string) $id, 'name' => $names[(int) $id] ?? ('应用 ' . $id)])->values(),
            'tags' => $tags,
        ];
    }

    private function present(object $row, bool $withRelations): array
    {
        $markets = $this->json($row->markets);
        $apps = $this->json($row->apps);
        $tags = $this->json($row->tags);
        $state = (string) $row->state;
        $record = [
            'id' => IpNormalizer::idFor($row->ip),
            'ip' => $row->ip,
            'ip_version' => IpNormalizer::version($row->ip),
            'state' => $state,
            'current_decision' => $row->current_decision,
            'markets' => array_map(fn ($item) => $item['label'] ?? $item['value'] ?? '', $markets),
            'market_items' => $markets,
            'apps' => array_map(fn ($app) => [
                'key' => (string) ($app['id'] ?? ''),
                'id' => (string) ($app['id'] ?? ''),
                'name' => $app['name'] ?? '',
            ], $apps),
            'device_count' => (int) $row->device_count,
            'account_count' => (int) $row->account_count,
            'tags' => array_map(fn ($tag) => [
                'key' => $tag['code'] ?? '',
                'code' => $tag['code'] ?? '',
                'name' => $tag['label'] ?? ReasonCatalog::label((string) ($tag['code'] ?? '')),
                'active' => (bool) ($tag['active'] ?? false),
            ], $tags),
            'active_scope_count' => (int) $row->active_scope_count,
            'collected_at' => $row->collected_at ? $this->iso($row->collected_at) : null,
            'collectedAt' => $row->collected_at ? Carbon::parse($row->collected_at)->getTimestampMs() : 0,
            'collectionKnown' => (bool) $row->collection_known,
            'first_risk_at' => $row->first_risk_at ? $this->iso($row->first_risk_at) : null,
            'last_activity_at' => $row->last_activity_at ? $this->iso($row->last_activity_at) : null,
            'last' => $row->last_activity_at,
            'levelLabel' => $state === 'released' ? '已解除' : ($state === 'high' ? '高风险' : '待观察'),
            'color' => $state === 'released' ? 'success' : ($state === 'high' ? 'error' : 'warning'),
            'status' => $state === 'released' ? 'IP 已解除' : ($row->current_decision ?: '处置中'),
            'events' => [],
        ];
        if ($withRelations) {
            $record['devices'] = $this->devices($row->ip);
            $record['accounts'] = $this->accounts($row->ip);
            $record['apps'] = $this->appRelations($row->ip, $record['apps']);
        } else {
            $record['devices'] = array_fill(0, (int) $row->device_count, ['key' => '', 'name' => '']);
            $record['accounts'] = array_fill(0, (int) $row->account_count, ['key' => '', 'name' => '']);
        }

        return $record;
    }

    private function devices(string $ip): array
    {
        $rows = DB::table('risk_scopes')->where('ip', $ip)->where('device_identity', '<>', '')
            ->selectRaw('device_identity, MIN(collected_at) as first_at, MAX(updated_at) as last_at, COUNT(DISTINCT app_id) as apps')
            ->groupBy('device_identity')->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'key' => $row->device_identity,
                'name' => $row->device_identity,
                'first' => $row->first_at,
                'last' => $row->last_at,
                'apps' => (int) $row->apps,
            ];
        }

        return $list;
    }

    private function accounts(string $ip): array
    {
        $names = SystemApp::idToNameMap();
        $rows = DB::table('risk_scopes')->where('ip', $ip)->whereNotNull('user_id')->where('user_id', '<>', '')
            ->selectRaw('app_id, user_id, MIN(collected_at) as first_at, MAX(updated_at) as last_at')
            ->groupBy('app_id', 'user_id')->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'key' => $row->app_id . ':' . $row->user_id,
                'name' => $row->user_id,
                'appName' => $names[(int) $row->app_id] ?? ('应用 ' . $row->app_id),
                'appId' => (string) $row->app_id,
                'first' => $row->first_at,
                'last' => $row->last_at,
            ];
        }

        return $list;
    }

    private function appRelations(string $ip, array $apps): array
    {
        $stats = DB::table('risk_scopes')->where('ip', $ip)
            ->selectRaw('app_id, COUNT(DISTINCT device_identity) as devices, COUNT(DISTINCT user_id) as accounts, MIN(collected_at) as first_at, MAX(updated_at) as last_at')
            ->groupBy('app_id')->get()->keyBy('app_id');
        foreach ($apps as &$app) {
            $stat = $stats[$app['id']] ?? null;
            $app['devices'] = $stat ? (int) $stat->devices : 0;
            $app['accounts'] = $stat ? (int) $stat->accounts : 0;
            $app['first'] = $stat->first_at ?? null;
            $app['last'] = $stat->last_at ?? null;
        }

        return $apps;
    }

    private function event(object $row): array
    {
        $codes = $this->ledger->codes($row->reason_codes);
        $tags = array_map(fn ($code) => ['code' => $code, 'label' => ReasonCatalog::label($code)], $codes);
        $at = Carbon::parse($row->occurred_at);
        $risky = in_array($row->event_type, ['risk_hit', 'intake', 'reintake'], true);

        return [
            'key' => 'id:' . $row->id,
            'id' => (string) $row->id,
            'ip' => $row->ip,
            'appKey' => $row->app_id ? (string) $row->app_id : '',
            'appId' => $row->app_id ? (string) $row->app_id : '',
            'appName' => $row->app_name ?: ($row->app_id ? '应用 ' . $row->app_id : ''),
            'market' => (string) $row->market_channel,
            'device' => (string) $row->device_identity,
            'account' => (string) $row->user_id,
            'version' => (string) $row->version_name,
            'login' => $row->event_type === 'login',
            'time' => $at->format('Y-m-d H:i:s'),
            'ms' => $at->getTimestamp() * 1000,
            'tags' => $tags,
            'risky' => $risky,
            'score' => $row->risk_score,
            'level' => (string) $row->risk_level,
            'decision' => (string) $row->decision,
            'kind' => (string) $row->event_type,
            'title' => (string) ($row->title ?: $row->event_type),
            'operation' => (string) ($row->title ?: ''),
            'operator' => (string) $row->operator_name,
            'remark' => '',
        ];
    }

    private function json($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function iso($value): string
    {
        return Carbon::parse($value)->timezone('Asia/Shanghai')->toIso8601String();
    }
}
