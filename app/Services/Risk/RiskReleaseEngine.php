<?php

namespace App\Services\Risk;

use App\Exceptions\AdminException;
use App\Models\RiskList;
use App\Models\SystemApp;
use App\Models\User;
use App\Services\Risk\Ledger\ReleaseRule;
use App\Services\Risk\Ledger\UsageWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskReleaseEngine
{
    public function __construct(private RiskLedgerService $ledger)
    {
    }

    public function evaluateDevice(int $appId, string $device): ?array
    {
        $scopes = DB::table('risk_scopes')
            ->where('app_id', $appId)
            ->where('device_identity', $device)
            ->where('state', 'active')
            ->where('policy_eligible', true)
            ->get();
        $released = [];
        foreach ($scopes as $scope) {
            $result = $this->tryRelease($scope);
            if ($result) {
                $released[] = $result;
            }
        }

        return $released ? ['released' => $released] : null;
    }

    public function tryRelease(object $scope): ?array
    {
        return DB::transaction(function () use ($scope) {
            $this->ledger->lockIp($scope->ip);
            $scope = DB::table('risk_scopes')->where('id', $scope->id)->lockForUpdate()->first();
            if (! $scope || $scope->state !== 'active' || ! (bool) $scope->policy_eligible) {
                return null;
            }
            if (DB::table('risk_activity_events')->where('app_id', $scope->app_id)->where('device_identity', $scope->device_identity)->whereNull('projected_at')->exists()) {
                return $this->skip($scope, 'activity_projection_pending');
            }
            if ($scope->auto_release_hold_version === ReleaseRule::VERSION && ! $scope->evidence_after_hold_at) {
                return $this->skip($scope, 'same_rule_hold');
            }
            $events = $this->activityEvents($scope);
            if ($events === []) {
                return $this->skip($scope, 'no_usage_evidence');
            }
            $measured = UsageWindow::measure($events);
            if (! $measured['qualified']) {
                return $this->skip($scope, 'usage_not_qualified');
            }
            $online = $this->onlineInteraction($scope, (int) $measured['qualifying_interaction_at'], $measured['session_id']);
            if (! $online) {
                return $this->skip($scope, 'interaction_not_online');
            }
            $blockers = $this->blockers($scope);
            foreach ($blockers as $blocker) {
                if (! empty($blocker['prevent']) || ! empty($blocker['pause'])) {
                    return $this->skip($scope, (string) $blocker['type']);
                }
            }
            $key = sprintf(
                'scope:%d:rule:%s:%s:session:%s',
                $scope->id,
                ReleaseRule::ID,
                ReleaseRule::VERSION,
                $measured['session_id']
            );
            $existing = DB::table('risk_releases')->where('idempotency_key', $key)->first();
            if ($existing) {
                return ['id' => (int) $existing->id, 'duplicate' => true];
            }

            $now = Carbon::now('Asia/Shanghai');
            $names = SystemApp::idToNameMap();
            $releaseId = DB::table('risk_releases')->insertGetId([
                'ip' => $scope->ip,
                'released_at' => $now->toDateTimeString(),
                'status' => 'released',
                'rule_id' => ReleaseRule::ID,
                'rule_version' => ReleaseRule::VERSION,
                'resource_version' => 1,
                'idempotency_key' => $key,
                'evidence' => json_encode([
                    'session_id' => $measured['session_id'],
                    'effective_foreground_seconds' => $measured['effective_seconds'],
                    'threshold_reached_at' => Carbon::createFromTimestamp($measured['threshold_reached_at'])->timezone('Asia/Shanghai')->toIso8601String(),
                    'last_interaction_at' => Carbon::createFromTimestamp($measured['qualifying_interaction_at'])->timezone('Asia/Shanghai')->toIso8601String(),
                    'hard_blockers' => [],
                    'received_time_bounded' => true,
                    'usage_protocol_version' => 2,
                ], JSON_UNESCAPED_UNICODE),
                'explanation' => ReleaseRule::describe((int) $measured['effective_seconds']),
                'ip_fully_released' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('risk_release_scopes')->insert([
                'release_id' => $releaseId,
                'scope_id' => $scope->id,
                'app_id' => $scope->app_id,
                'app_name' => $names[(int) $scope->app_id] ?? '',
                'device_identity' => $scope->device_identity,
                'install_id' => $scope->install_id,
                'user_id' => $scope->user_id,
                'market_channel' => $scope->market_channel,
                'reason_codes' => $scope->reason_codes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('risk_scopes')->where('id', $scope->id)->update([
                'state' => 'released',
                'released_at' => $now->toDateTimeString(),
                'release_id' => $releaseId,
                'updated_at' => now(),
            ]);
            $this->ledger->scopeEvent((int) $scope->id, $scope->ip, 'release', $now, false, $releaseId);
            $activeLeft = DB::table('risk_scopes')->where('ip', $scope->ip)->where('state', 'active')->where('policy_eligible', true)->count();
            $fully = $activeLeft === 0;
            if ($fully) {
                DB::table('risk_ip_exits')->insert([
                    'ip' => $scope->ip,
                    'exited_at' => $now->toDateTimeString(),
                    'release_id' => $releaseId,
                    'status' => 'effective',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('risk_releases')->where('id', $releaseId)->update(['ip_fully_released' => $fully ? 1 : 0]);
            $this->ledger->timeline(
                $scope->ip,
                'auto_release',
                $now,
                null,
                (string) $scope->device_identity,
                $this->ledger->codes($scope->reason_codes),
                (string) $scope->decision,
                (string) $scope->risk_level,
                (int) $scope->id,
                '自动释放',
                $releaseId,
                'system',
                '自动规则引擎'
            );
            $this->ledger->refreshDossier($scope->ip);

            return ['id' => $releaseId, 'ip_fully_released' => $fully];
        });
    }

    public function revoke(int $releaseId, string $requestId, int $expectedVersion, int $operatorId, string $operatorName): array
    {
        if ($requestId === '') {
            throw new AdminException('缺少撤销请求号');
        }

        return DB::transaction(function () use ($releaseId, $requestId, $expectedVersion, $operatorId, $operatorName) {
            $prior = DB::table('risk_revocations')->where('request_id', $requestId)->first();
            if ($prior && (int) $prior->release_id !== $releaseId) {
                throw new AdminException('请求号已用于另一条释放记录', 409);
            }
            if ($prior) {
                $release = DB::table('risk_releases')->where('id', $releaseId)->first();

                return $this->revokePayload($release, $prior->result, false);
            }
            $ip = DB::table('risk_releases')->where('id', $releaseId)->value('ip');
            if ($ip) {
                $this->ledger->lockIp($ip);
            }
            $release = DB::table('risk_releases')->where('id', $releaseId)->lockForUpdate()->first();
            if (! $release) {
                throw new AdminException('释放记录不存在');
            }
            // A concurrent retry may have committed while this request waited for the IP lock.
            $prior = DB::table('risk_revocations')->where('request_id', $requestId)->lockForUpdate()->first();
            if ($prior) {
                if ((int) $prior->release_id !== $releaseId) {
                    throw new AdminException('请求号已用于另一条释放记录', 409);
                }

                return $this->revokePayload($release, $prior->result, false);
            }
            if ((int) $release->resource_version !== $expectedVersion) {
                throw new AdminException('释放记录已变化，请刷新后重试', 409);
            }
            if ($release->status === 'revoked') {
                $this->storeRevocation($releaseId, $requestId, $operatorId, $operatorName, 'already_revoked', []);

                return $this->revokePayload($release, 'already_revoked', false);
            }
            $links = DB::table('risk_release_scopes')->where('release_id', $releaseId)->get();
            $restored = [];
            $skipped = [];
            $now = Carbon::now('Asia/Shanghai');
            foreach ($links as $link) {
                $scope = DB::table('risk_scopes')->where('id', $link->scope_id)->lockForUpdate()->first();
                if (! $scope || (int) $scope->release_id !== $releaseId || $scope->state !== 'released') {
                    $skipped[] = (int) $link->scope_id;

                    continue;
                }
                $newer = DB::table('risk_scopes')
                    ->where('app_id', $scope->app_id)
                    ->where('device_identity', $scope->device_identity)
                    ->where('ip', $scope->ip)
                    ->where('policy_eligible', true)
                    ->where('state', 'active')
                    ->exists();
                if ($newer) {
                    $skipped[] = (int) $scope->id;

                    continue;
                }
                DB::table('risk_scopes')->where('id', $scope->id)->update([
                    'state' => 'active',
                    'released_at' => null,
                    'release_id' => null,
                    'auto_release_hold_version' => ReleaseRule::VERSION,
                    'hold_at' => $now->toDateTimeString(),
                    'evidence_after_hold_at' => null,
                    'updated_at' => now(),
                ]);
                $this->ledger->scopeEvent((int) $scope->id, $scope->ip, 'revoke', $now, true, $releaseId);
                $restored[] = (int) $scope->id;
            }
            $result = $restored === [] ? 'not_restored' : ($skipped === [] ? 'restored' : 'partial');
            if ($restored !== []) {
                DB::table('risk_releases')->where('id', $releaseId)->update([
                    'status' => 'revoked',
                    'resource_version' => (int) $release->resource_version + 1,
                    'updated_at' => now(),
                ]);
                if ((int) $release->ip_fully_released === 1) {
                    DB::table('risk_ip_exits')->where('release_id', $releaseId)->where('status', 'effective')->update([
                        'status' => 'revoked',
                        'revoked_at' => $now->toDateTimeString(),
                        'updated_at' => now(),
                    ]);
                }
                $this->ledger->timeline(
                    $release->ip,
                    'release_revoke',
                    $now,
                    null,
                    (string) ($links->first()->device_identity ?? ''),
                    [],
                    '',
                    '',
                    $restored[0] ?? null,
                    '撤销释放',
                    $releaseId,
                    'admin',
                    $operatorName
                );
                $this->ledger->refreshDossier($release->ip);
            }
            $this->storeRevocation($releaseId, $requestId, $operatorId, $operatorName, $result, [
                'restored_scope_ids' => $restored,
                'skipped_scope_ids' => $skipped,
            ]);
            $fresh = DB::table('risk_releases')->where('id', $releaseId)->first();

            return $this->revokePayload($fresh, $result, true);
        });
    }

    private function storeRevocation(int $releaseId, string $requestId, int $operatorId, string $operatorName, string $result, array $detail): void
    {
        DB::table('risk_revocations')->insert([
            'release_id' => $releaseId,
            'request_id' => $requestId,
            'operator_id' => $operatorId,
            'operator_name' => $operatorName,
            'revoked_at' => Carbon::now('Asia/Shanghai')->toDateTimeString(),
            'result' => $result,
            'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function revokePayload(?object $release, string $result, bool $changed): array
    {
        return [
            'id' => $release ? (int) $release->id : 0,
            'status' => $release->status ?? 'released',
            'resource_version' => $release ? (int) $release->resource_version : 0,
            'result' => $result,
            'changed' => $changed && $result !== 'already_revoked',
            'can_revoke' => $release && $release->status === 'released',
        ];
    }

    private function skip(object $scope, string $reason)
    {
        DB::table('risk_scopes')->where('id', $scope->id)->update([
            'last_skip_reason' => $reason,
            'last_skip_at' => Carbon::now('Asia/Shanghai')->toDateTimeString(),
        ]);

        return null;
    }

    private function activityEvents(object $scope): array
    {
        $session = DB::table('risk_activity_events')->where('app_id', $scope->app_id)
            ->where('device_identity', $scope->device_identity)->where('online_verified', 1)->orderByDesc('id')->value('session_id');
        if (! $session) {
            return [];
        }
        $rows = DB::table('risk_activity_events')
            ->where('session_id', $session)
            ->where('app_id', $scope->app_id)
            ->where('device_identity', $scope->device_identity)
            ->where('occurred_at', '>=', $scope->hold_at ?? $scope->collected_at)
            ->where('online_verified', 1)
            ->orderByDesc('id')
            ->limit(5001)
            ->get();
        if ($rows->count() > 5000) {
            return [];
        }
        $events = [];
        foreach ($rows as $row) {
            if ($row->foreground === null || $row->screen_interactive === null) {
                continue;
            }
            $events[] = [
                'at' => Carbon::parse($row->occurred_at)->timezone('Asia/Shanghai')->getTimestamp(),
                'seq' => (int) $row->event_seq,
                'received_at' => Carbon::parse($row->received_at)->timestamp,
                'monotonic_ms' => $row->monotonic_elapsed_ms === null ? null : (int) $row->monotonic_elapsed_ms,
                'session_id' => (string) $row->session_id,
                'foreground' => (bool) $row->foreground,
                'screen_interactive' => (bool) $row->screen_interactive,
                'interaction' => (bool) $row->interaction,
            ];
        }

        return $events;
    }

    private function onlineInteraction(object $scope, int $at, ?string $sessionId = null): bool
    {
        $stamp = Carbon::createFromTimestamp($at)->timezone('Asia/Shanghai')->toDateTimeString();
        $row = DB::table('risk_activity_events')
            ->where('app_id', $scope->app_id)
            ->where('device_identity', $scope->device_identity)
            ->where('interaction', 1)
            ->where('online_verified', 1)
            ->when($sessionId !== null, fn ($q) => $q->where('session_id', $sessionId))
            ->where('occurred_at', $stamp)
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return false;
        }
        $occurred = Carbon::parse($row->occurred_at)->getTimestamp();
        $received = Carbon::parse($row->received_at)->getTimestamp();

        return abs($received - $occurred) <= ReleaseRule::ONLINE_SKEW_SECONDS
            && abs(Carbon::now('Asia/Shanghai')->timestamp - $received) <= ReleaseRule::ONLINE_SKEW_SECONDS;
    }

    /**
     * @return array<int, array{type:string,prevent?:bool,pause?:bool}>
     */
    public function blockers(object $scope, bool $readOnly = false): array
    {
        $blocks = [];
        if ($this->matchesMonitorAccount($scope)) {
            $blocks[] = ['type' => 'test_account', 'prevent' => true];
        }
        if ($this->explicitPrevent($scope)) {
            $blocks[] = ['type' => 'prevent_auto_release', 'prevent' => true];
        }
        $review = $this->reviewBlock($scope, $readOnly);
        if ($review) {
            $blocks[] = $review;
        }

        return $blocks;
    }

    private function matchesMonitorAccount(object $scope): bool
    {
        $accounts = DB::table('risk_monitor_accounts')->where('status', 1)->whereIn('app_id', [0, (int) $scope->app_id])->pluck('account')->all();
        if ($accounts === []) {
            return false;
        }
        if ($scope->user_id && in_array((string) $scope->user_id, $accounts, true)) {
            return true;
        }
        $user = User::query()
            ->where('app_id', $scope->app_id)
            ->where('uuid', $scope->device_identity)
            ->where(function ($query) use ($accounts) {
                $query->whereIn('phone', $accounts)->orWhereIn('account', $accounts);
            })
            ->exists();

        return $user;
    }

    private function explicitPrevent(object $scope): bool
    {
        $match = RiskListService::match((string) $scope->device_identity, (string) $scope->install_id, (int) $scope->app_id);
        if ($match && $match['list_type'] === RiskList::TYPE_BLACKLIST) {
            return true;
        }

        return RiskList::query()
            ->where('status', 1)
            ->where('decision', 'block')
            ->where(function ($query) use ($scope) {
                $query->where(function ($inner) use ($scope) {
                    $inner->where('target_type', 'device_identity')->where('target_value', $scope->device_identity);
                })->orWhere(function ($inner) use ($scope) {
                    $inner->where('target_type', 'account')->where('target_value', (string) $scope->user_id);
                });
            })
            ->whereIn('app_id', [0, (int) $scope->app_id])
            ->exists();
    }

    private function reviewBlock(object $scope, bool $readOnly = false): ?array
    {
        $evidence = app(RiskReviewEvidence::class);
        if ($evidence->locked((int) $scope->app_id, (string) $scope->device_identity)) {
            $kind = DB::table('risk_hard_blocks')->where('app_id', $scope->app_id)->where('device_identity', $scope->device_identity)->whereNull('invalidated_at')->value('kind');

            return ['type' => $kind, 'prevent' => true];
        }
        $method = $readOnly ? 'statusAt' : 'observe';
        $result = $evidence->{$method}((int) $scope->app_id, (string) $scope->device_identity,
            (string) $scope->version_name, (string) $scope->market_channel, Carbon::parse($scope->collected_at));

        return match ($result) {
            'blocked' => ['type' => 'reviewing_visit', 'prevent' => true],
            'unknown' => ['type' => 'review_status_unknown', 'pause' => true],
            default => null,
        };
    }

    private function versionKey(?string $version): string
    {
        return ltrim(strtolower(trim((string) $version)), 'v');
    }
}
