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
        $count = $counts[in_array($filter['state'] ?? '', ['high', 'watch', 'released'], true) ? $filter['state'] : 'all'];
        $rows = $query->orderByDesc('last_activity_at')->orderBy('ip')
            ->forPage(max(1, $page), max(1, min(100, $limit)))
            ->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->present($row, false, ! empty($filter['compact']));
        }

        return [
            'list' => $list,
            'count' => $count,
            'state_counts' => $counts,
            'options' => filter_var($filter['include_options'] ?? true, FILTER_VALIDATE_BOOLEAN) ? $this->options() : null,
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
            'complete' => $this->ledger->caughtUp(),
        ];
    }

    public function detail(string $id, bool $withRelations = true): ?array
    {
        $ip = IpNormalizer::ipFor($id);
        $row = $ip === '' ? null : DB::table('risk_ip_dossiers')->where('ip', $ip)->first();
        if (! $row) {
            return null;
        }
        $presented = $this->present($row, $withRelations, ! $withRelations);
        if (! $withRelations) {
            return $presented;
        }
        $presented['scopes'] = DB::table('risk_scopes')->where('ip', $ip)->orderByDesc('id')->get()->map(function ($scope) {
            return [
                'scope_id' => (int) $scope->id,
                'policy_eligible' => (bool) $scope->policy_eligible,
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
        $ip = IpNormalizer::ipFor($id);
        $row = $ip === '' ? null : DB::table('risk_ip_dossiers')->where('ip', $ip)->first();
        if (! $row) {
            return ['list' => [], 'count' => 0, 'has_more' => false];
        }
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        if ($kind === 'devices' || $kind === 'accounts') {
            $devices = $kind === 'devices';
            $query = DB::table('risk_scopes')->where('ip', $ip);
            if ($devices) {
                $identity = DB::connection()->getDriverName() === 'sqlite' ? "app_id || ':' || device_identity" : "CONCAT(app_id, ':', device_identity)";
                $key = "CASE WHEN identity_kind = 'device' THEN device_identity ELSE ".$identity.' END';
                $query->where('device_identity', '<>', '')
                    ->selectRaw($key.' as identity_key, MIN(device_identity) as device_identity, MIN(collected_at) as first_at, MAX(updated_at) as last_at, COUNT(DISTINCT app_id) as apps, GROUP_CONCAT(DISTINCT app_id) as app_ids')
                    ->groupByRaw($key);
            } else {
                $query = $this->accountRelationsQuery($ip);
            }
            $count = (int) DB::query()->fromSub(clone $query, 'relations')->count();
            $query->orderBy($devices ? 'identity_key' : 'app_id');
            if (! $devices) {
                $query->orderBy('user_id');
            }
            $names = $devices ? [] : SystemApp::idToNameMap();
            $pageRows = $query->forPage($page, $limit)->get();
            $deviceAccounts = collect();
            $deviceEvents = collect();
            if ($devices && $pageRows->isNotEmpty()) {
                $concat = DB::connection()->getDriverName() === 'sqlite' ? "s.app_id || ':' || s.device_identity" : "CONCAT(s.app_id, ':', s.device_identity)";
                $deviceKey = "CASE WHEN s.identity_kind = 'device' THEN s.device_identity ELSE $concat END";
                $scopes = DB::table('risk_scopes as s')->where('s.ip', $ip)->whereIn('s.device_identity', $pageRows->pluck('device_identity')->all());
                $original = (clone $scopes)->whereNotNull('s.user_id')->where('s.user_id', '<>', '')
                    ->selectRaw($deviceKey.' as identity_key, s.app_id, s.user_id');
                $links = (clone $scopes)->join('risk_scope_accounts as a', 'a.scope_id', '=', 's.id')
                    ->selectRaw($deviceKey.' as identity_key, a.app_id, a.user_id');
                $deviceAccounts = DB::query()->fromSub($original->union($links), 'accounts')->selectRaw('identity_key, COUNT(*) as n')->groupBy('identity_key')->pluck('n', 'identity_key');
                $deviceEvents = (clone $scopes)->join('risk_timeline as t', function ($join) {
                    $join->on('t.ip', '=', 's.ip')->on('t.app_id', '=', 's.app_id')->on('t.device_identity', '=', 's.device_identity');
                })->selectRaw($deviceKey.' as identity_key, COUNT(DISTINCT t.id) as n')->groupByRaw($deviceKey)->pluck('n', 'identity_key');
            }
            $list = $pageRows->map(function ($item) use ($devices, $names, $deviceAccounts, $deviceEvents) {
                $base = ['first' => $item->first_at, 'last' => $item->last_at];

                return $devices ? $base + ['key' => $item->identity_key, 'name' => $item->device_identity, 'apps' => (int) $item->apps, 'app_ids' => explode(',', $item->app_ids), 'account_count' => (int) ($deviceAccounts[$item->identity_key] ?? 0), 'event_count' => (int) ($deviceEvents[$item->identity_key] ?? 0)]
                    : $base + ['key' => $item->app_id.':'.$item->user_id, 'name' => $item->user_id,
                        'appId' => (string) $item->app_id, 'appName' => $names[(int) $item->app_id] ?? ('应用 '.$item->app_id), 'devices' => explode(',', (string) $item->devices)];
            })->all();
        } else {
            // App/tag metadata is bounded by the dossier, never load device/account scopes.
            $record = $this->present($row, false, true);
            $all = $kind === 'tags' ? $record['tags'] : $this->appRelations($ip, $record['apps']);
            if ($kind === 'tags') {
                $facts = DB::table('risk_reason_evidence')->where('ip', $ip)->selectRaw('code, SUM(hit_count) as hits, MIN(first_at) as first_at, MAX(last_at) as last_at, COUNT(DISTINCT device_identity) as devices, GROUP_CONCAT(DISTINCT app_id) as app_ids')->groupBy('code')->get()->keyBy('code');
                foreach ($all as &$tag) {
                    $fact = $facts[$tag['code']] ?? null;
                    if ($fact) {
                        $tag += ['count' => (int) $fact->hits, 'first' => $fact->first_at, 'last' => $fact->last_at, 'apps' => explode(',', $fact->app_ids), 'device_count' => (int) $fact->devices];
                    }
                }
                unset($tag);
            }
            $count = count($all);
            $list = array_slice($all, ($page - 1) * $limit, $limit);
        }

        return ['list' => $list, 'count' => $count, 'has_more' => $page * $limit < $count];
    }

    public function timeline(string $id, array $filter, int $page, int $limit): ?array
    {
        $ip = IpNormalizer::ipFor($id);
        if ($ip === '' || ! DB::table('risk_ip_dossiers')->where('ip', $ip)->exists()) {
            return null;
        }
        $query = DB::table('risk_timeline')->where('ip', $ip);
        if (! empty($filter['day'])) {
            $query->where('occurred_at', '>=', $filter['day'].' 00:00:00')
                ->where('occurred_at', '<', Carbon::parse($filter['day'])->addDay()->toDateString().' 00:00:00');
        }
        if (! empty($filter['app_id'])) {
            $query->where('app_id', $filter['app_id']);
        }
        if (! empty($filter['event_kind'])) {
            $kinds = ['risk' => ['risk_hit', 'intake', 'reintake'], 'activity' => ['login', 'access'], 'manual' => ['auto_release', 'release_revoke']];
            $query->whereIn('event_type', $kinds[$filter['event_kind']] ?? [$filter['event_kind']]);
        }
        if (! empty($filter['reason_code'])) {
            $query->whereJsonContains('reason_codes', (string) $filter['reason_code']);
        }
        $count = (clone $query)->count();
        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->forPage(max(1, $page), max(1, min(100, $limit)))->get();
        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->event($row);
        }
        $days = filter_var($filter['include_days'] ?? true, FILTER_VALIDATE_BOOLEAN) ? DB::table('risk_timeline')
            ->where('ip', $ip)
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
            ->selectRaw("SUM(event_type IN ('risk_hit','intake','reintake')) as risk_count")
            ->selectRaw("SUM(event_type IN ('auto_release','release_revoke')) as action_count")
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('day')
            ->get() : null;

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
            $normalized = IpNormalizer::normalize($keyword);
            if ($normalized !== '') {
                $query->where('ip', $normalized);
            } else {
                $like = '%'.addcslashes($keyword, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('ip', 'like', $like)->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')->from('risk_scopes')
                            ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                            ->where(function ($scope) use ($like) {
                                $scope->where('device_identity', 'like', $like)->orWhere('user_id', 'like', $like);
                            });
                    });
                });
            }
        }
        if (! empty($filter['market_channel'])) {
            $channel = (string) $filter['market_channel'];
            $query->whereExists(function ($sub) use ($channel) {
                $sub->selectRaw('1')->from('risk_scopes')
                    ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                    ->where('market_channel', $channel);
            });
        }
        if (! empty($filter['app_id'])) {
            $appId = (int) $filter['app_id'];
            $query->whereExists(function ($sub) use ($appId) {
                $sub->selectRaw('1')->from('risk_scopes')
                    ->whereColumn('risk_scopes.ip', 'risk_ip_dossiers.ip')
                    ->where('app_id', $appId);
            });
        }
        if (! empty($filter['reason_code'])) {
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $filter['reason_code']));
            if ($code !== '') {
                $query->whereJsonContains('tags', ['code' => $code]);
            }
        }
        if (! $ignoreState && ! empty($filter['state']) && in_array($filter['state'], ['high', 'watch', 'released'], true)) {
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
            'all' => (int) $rows->sum(),
            'high' => $high,
            'watch' => $watch,
            'released' => $released,
        ];
    }

    public function options(): array
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
            'apps' => $apps->map(fn ($id) => ['id' => (string) $id, 'name' => $names[(int) $id] ?? ('应用 '.$id)])->values(),
            'tags' => $tags,
        ];
    }

    private function present(object $row, bool $withRelations, bool $compact = false): array
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
            $record['devices'] = array_fill(0, $compact ? 0 : (int) $row->device_count, ['key' => '', 'name' => '']);
            $record['accounts'] = array_fill(0, $compact ? 0 : (int) $row->account_count, ['key' => '', 'name' => '']);
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

    private function accountRelationsQuery(string $ip)
    {
        $legacy = DB::table('risk_scopes')->where('ip', $ip)->whereNotNull('user_id')->where('user_id', '<>', '')
            ->select('app_id', 'user_id', 'device_identity', 'collected_at as first_at', 'updated_at as last_at');
        $links = DB::table('risk_scope_accounts as a')->join('risk_scopes as s', 's.id', '=', 'a.scope_id')->where('s.ip', $ip)
            ->select('a.app_id', 'a.user_id', 's.device_identity', 'a.first_at', 'a.last_at');

        return DB::query()->fromSub($legacy->unionAll($links), 'accounts')->selectRaw('app_id, user_id, MIN(first_at) as first_at, MAX(last_at) as last_at, GROUP_CONCAT(DISTINCT device_identity) as devices')
            ->groupBy('app_id', 'user_id');
    }

    private function accounts(string $ip): array
    {
        $names = SystemApp::idToNameMap();
        $rows = $this->accountRelationsQuery($ip)->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'key' => $row->app_id.':'.$row->user_id,
                'name' => $row->user_id,
                'appName' => $names[(int) $row->app_id] ?? ('应用 '.$row->app_id),
                'appId' => (string) $row->app_id,
                'first' => $row->first_at,
                'last' => $row->last_at,
                'devices' => explode(',', (string) $row->devices),
            ];
        }

        return $list;
    }

    private function appRelations(string $ip, array $apps): array
    {
        $stats = DB::table('risk_scopes')->where('ip', $ip)
            ->selectRaw('app_id, COUNT(DISTINCT device_identity) as devices, COUNT(DISTINCT user_id) as accounts, MIN(collected_at) as first_at, MAX(updated_at) as last_at')
            ->groupBy('app_id')->get()->keyBy('app_id');
        $accountCounts = DB::query()->fromSub($this->accountRelationsQuery($ip), 'accounts')->selectRaw('app_id, COUNT(*) as total')->groupBy('app_id')->pluck('total', 'app_id');
        $markets = DB::table('risk_scopes')->where('ip', $ip)->whereNotNull('market_channel')->where('market_channel', '<>', '')->selectRaw('app_id, GROUP_CONCAT(DISTINCT market_channel) as markets')->groupBy('app_id')->pluck('markets', 'app_id');
        foreach ($apps as &$app) {
            $stat = $stats[$app['id']] ?? null;
            $app['devices'] = $stat ? (int) $stat->devices : 0;
            $app['accounts'] = (int) ($accountCounts[$app['id']] ?? 0);
            $app['markets'] = isset($markets[$app['id']]) ? explode(',', $markets[$app['id']]) : [];
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
            'key' => 'id:'.$row->id,
            'id' => (string) $row->id,
            'ip' => $row->ip,
            'appKey' => $row->app_id ? (string) $row->app_id : '',
            'appId' => $row->app_id ? (string) $row->app_id : '',
            'appName' => $row->app_name ?: ($row->app_id ? '应用 '.$row->app_id : ''),
            'market' => (string) $row->market_channel,
            'device' => (string) $row->device_identity,
            'account' => (string) $row->user_id,
            'version' => (string) $row->version_name,
            'login' => in_array($row->event_type, ['login', 'access'], true),
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
