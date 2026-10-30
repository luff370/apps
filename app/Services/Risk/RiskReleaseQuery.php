<?php

namespace App\Services\Risk;

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\Ledger\ReasonCatalog;
use App\Services\Risk\Ledger\ReleaseRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskReleaseQuery
{
    public function list(array $filter, int $page, int $limit): array
    {
        $query = DB::table('risk_releases');
        $this->apply($query, $filter);
        $count = (clone $query)->count();
        $rows = $query->orderByDesc('released_at')->orderByDesc('id')->forPage($page, $limit)->get();
        $ids = $rows->pluck('id')->all();
        $scopes = $ids === [] ? collect() : DB::table('risk_release_scopes')->whereIn('release_id', $ids)->get()->groupBy('release_id');
        $latestRevocations = DB::table('risk_revocations')->whereIn('release_id', $ids)
            ->selectRaw('MAX(id) as id')->groupBy('release_id');
        $revocations = $ids === [] ? collect() : DB::table('risk_revocations')
            ->whereIn('id', $latestRevocations)->get()->keyBy('release_id');
        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->present($row, false, $scopes->get($row->id, collect()), $revocations->get($row->id));
        }

        return [
            'list' => $list,
            'count' => $count,
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
        ];
    }

    public function detail(int $id): ?array
    {
        $row = DB::table('risk_releases')->where('id', $id)->first();

        return $row ? $this->present($row, true) : null;
    }

    private function apply($query, array $filter): void
    {
        if (! empty($filter['ip'])) {
            $ip = trim((string) $filter['ip']);
            $normalized = IpNormalizer::normalize($ip);
            if ($normalized !== '') {
                $query->where('ip', $normalized);
            } else {
                $query->where('ip', 'like', '%'.addcslashes($ip, '%_\\').'%');
            }
        }
        if (! empty($filter['app_id'])) {
            $appId = (string) $filter['app_id'];
            $query->whereExists(function ($sub) use ($appId) {
                $sub->selectRaw('1')->from('risk_release_scopes')
                    ->whereColumn('risk_release_scopes.release_id', 'risk_releases.id')
                    ->where('app_id', $appId);
            });
        }
        if (! empty($filter['start_date'])) {
            $query->where('released_at', '>=', Carbon::parse($filter['start_date'], 'Asia/Shanghai')->startOfDay()->toDateTimeString());
        }
        if (! empty($filter['end_date'])) {
            $query->where('released_at', '<', Carbon::parse($filter['end_date'], 'Asia/Shanghai')->addDay()->startOfDay()->toDateTimeString());
        }
        foreach (['device_keyword' => 'device_identity', 'user_keyword' => 'user_id'] as $input => $column) {
            if (! empty($filter[$input])) {
                $like = '%'.addcslashes(trim((string) $filter[$input]), '%_\\').'%';
                $query->whereExists(function ($sub) use ($column, $like) {
                    $sub->selectRaw('1')->from('risk_release_scopes')
                        ->whereColumn('risk_release_scopes.release_id', 'risk_releases.id')
                        ->where($column, 'like', $like);
                });
            }
        }
        if (! empty($filter['unregistered'])) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('risk_release_scopes')
                    ->whereColumn('risk_release_scopes.release_id', 'risk_releases.id')
                    ->where(function ($inner) {
                        $inner->whereNull('user_id')->orWhere('user_id', '');
                    });
            });
        }
        if (! empty($filter['market_channel'])) {
            $channel = (string) $filter['market_channel'];
            $query->whereExists(function ($sub) use ($channel) {
                $sub->selectRaw('1')->from('risk_release_scopes')
                    ->whereColumn('risk_release_scopes.release_id', 'risk_releases.id')
                    ->where('market_channel', $channel);
            });
        }
        if (! empty($filter['reason_code'])) {
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $filter['reason_code']));
            if ($code !== '') {
                $query->whereExists(function ($sub) use ($code) {
                    $sub->selectRaw('1')->from('risk_release_scopes')
                        ->whereColumn('risk_release_scopes.release_id', 'risk_releases.id')
                        ->where('reason_codes', 'like', '%"'.$code.'"%');
                });
            }
        }
    }

    private function present(object $row, bool $full, ?Collection $scopes = null, ?object $revocation = null): array
    {
        if ($scopes === null) {
            $scopes = DB::table('risk_release_scopes')->where('release_id', $row->id)->get();
            $revocation = DB::table('risk_revocations')->where('release_id', $row->id)->orderByDesc('id')->first();
        }
        $apps = [];
        $devices = [];
        $markets = [];
        $tags = [];
        $account = '';
        foreach ($scopes as $scope) {
            $apps[(string) $scope->app_id] = ['id' => (string) $scope->app_id, 'name' => (string) $scope->app_name];
            if ($scope->device_identity) {
                $devices[$scope->device_identity] = $scope->device_identity;
            }
            if ($scope->market_channel) {
                $markets[$scope->market_channel] = $scope->market_channel;
            }
            foreach (json_decode((string) $scope->reason_codes, true) ?: [] as $code) {
                $tags[$code] = $code;
            }
            if ($account === '' && $scope->user_id) {
                $account = (string) $scope->user_id;
            }
        }
        $releasedAt = Carbon::parse($row->released_at)->timezone('Asia/Shanghai');
        $evidence = json_decode((string) $row->evidence, true) ?: [];
        $payload = [
            'id' => (string) $row->id,
            'resource_version' => (int) $row->resource_version,
            'ip' => $row->ip,
            'released_at' => $releasedAt->toIso8601String(),
            'day' => $releasedAt->format('Y-m-d'),
            'time' => $releasedAt->format('H:i:s'),
            'status' => $row->status,
            'can_revoke' => $row->status === 'released',
            'rule' => '连续使用达到 30 分钟',
            'code' => $row->rule_id ?: ReleaseRule::ID,
            'version' => 'v'.($row->rule_version ?: ReleaseRule::VERSION),
            'condition' => (string) $row->explanation,
            'riskTags' => array_values($tags),
            'markets' => array_values($markets),
            'apps' => array_values($apps),
            'devices' => array_values($devices),
            'account' => $account !== '' ? $account : '未注册账号',
            'ip_fully_released' => (bool) $row->ip_fully_released,
            'evidence' => $evidence,
            'revocation' => $revocation ? [
                'at' => Carbon::parse($revocation->revoked_at)->timezone('Asia/Shanghai')->toIso8601String(),
                'operator' => $revocation->operator_name,
                'result' => $revocation->result,
            ] : null,
            'timeline' => $this->timeline($row, $revocation, $releasedAt),
        ];
        if ($full) {
            $payload['timeline'] = $this->timeline($row, $revocation, $releasedAt);
            $payload['released_scopes'] = $scopes->map(fn ($scope) => [
                'scope_id' => (int) $scope->scope_id,
                'app_id' => (string) $scope->app_id,
                'app_name' => $scope->app_name,
                'device_identity' => $scope->device_identity,
                'install_id' => $scope->install_id,
                'user_id' => $scope->user_id,
                'market_channel' => $scope->market_channel,
                'reason_codes' => json_decode((string) $scope->reason_codes, true) ?: [],
            ])->all();
            $payload['tag_labels'] = array_map(fn ($code) => ['code' => $code, 'label' => ReasonCatalog::label($code)], array_values($tags));
        }

        return $payload;
    }

    private function timeline(object $row, ?object $revocation, Carbon $releasedAt): array
    {
        $items = [[
            'title' => '自动释放完成',
            'at' => $releasedAt->format('Y-m-d H:i:s'),
            'operator' => '自动规则引擎',
            'detail' => (string) $row->explanation,
        ]];
        if ($revocation && in_array($revocation->result, ['restored', 'partial', 'already_revoked'], true)) {
            array_unshift($items, [
                'title' => '撤销释放 · 恢复原处置',
                'at' => Carbon::parse($revocation->revoked_at)->timezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                'operator' => (string) ($revocation->operator_name ?: '管理员'),
                'detail' => '同一规则版本在出现新证据前不会再次自动释放该范围',
            ]);
        }

        return $items;
    }
}
