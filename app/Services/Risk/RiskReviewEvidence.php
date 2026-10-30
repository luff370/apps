<?php

namespace App\Services\Risk;

use App\Models\AppVersionPlanTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Append-only review evidence. Current task edits cannot erase a past reviewing visit. */
class RiskReviewEvidence
{
    private array $captured = [];

    private array $firstHistory = [];

    public function captureApp(int $appId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('risk_review_history')) {
            return;
        }
        $this->captured[$appId] = true;
        $tasks = AppVersionPlanTask::query()->select('app_version_plan_tasks.*')
            ->join('app_version_plans', 'app_version_plans.id', '=', 'app_version_plan_tasks.plan_id')
            ->where('app_version_plans.app_id', $appId)->get();
        $latest = DB::table('risk_review_history')->where('app_id', $appId)->orderByDesc('id')->get()->unique('attempt_key')->keyBy('attempt_key');
        $present = [];
        foreach ($tasks as $task) {
            $version = ltrim(strtolower(trim((string) $task->version)), 'v');
            $channel = strtolower((string) $task->market_channel);
            $key = hash('sha256', json_encode([$appId, $task->plan_id, $version, $channel, $task->submitted_at]));
            $present[$key] = true;
            $value = ['app_id' => $appId, 'attempt_key' => $key, 'version_name' => $version,
                'market_channel' => $channel, 'submitted_at' => $task->submitted_at,
                'listed_at' => $task->listed_at, 'status' => $task->status];
            $old = $latest[$key] ?? null;
            if (! $old || $old->status !== $value['status'] || $old->listed_at !== $value['listed_at']) {
                DB::table('risk_review_history')->insert($value + ['effective_at' => Carbon::now('Asia/Shanghai')->toDateTimeString()]);
            }
        }
        unset($this->firstHistory[$appId]);
        foreach ($latest as $key => $old) {
            if (! isset($present[$key]) && $old->status !== 'retired') {
                $value = (array) $old;
                unset($value['id']);
                $value['status'] = 'retired';
                $value['effective_at'] = Carbon::now('Asia/Shanghai')->toDateTimeString();
                DB::table('risk_review_history')->insert($value);
            }
        }
    }

    public function canSkipHistorical(int $appId, string $at): bool
    {
        if (! isset($this->captured[$appId])) {
            $this->captureApp($appId);
        }
        if (! array_key_exists($appId, $this->firstHistory)) {
            $this->firstHistory[$appId] = DB::table('risk_review_history')->where('app_id', $appId)->min('effective_at');
        }

        return $this->firstHistory[$appId] === null || $at < $this->firstHistory[$appId];
    }

    public function observe(int $appId, string $device, string $version, string $channel, Carbon $at): string
    {
        if ($version === '' || $channel === '') {
            return 'unknown';
        }
        if (! isset($this->captured[$appId])) {
            $this->captureApp($appId);
        }

        return $this->statusAt($appId, $device, $version, $channel, $at, true);
    }

    /** Read-only policy check: request handling must never capture plans or write hard evidence. */
    public function statusAt(int $appId, string $device, string $version, string $channel, Carbon $at, bool $persist = false): string
    {
        if ($version === '' || $channel === '') {
            return 'unknown';
        }
        $history = DB::table('risk_review_history')->where('app_id', $appId)
            ->where('version_name', ltrim(strtolower(trim($version)), 'v'))->where('market_channel', strtolower($channel))
            ->where('effective_at', '<=', $at->toDateTimeString())->orderByDesc('id')->get()->unique('attempt_key')
            ->filter(fn ($r) => $r->submitted_at && $r->submitted_at <= $at->toDateTimeString() && $r->status !== 'retired')
            ->sortByDesc('submitted_at')->values();
        if ($history->isEmpty()) {
            // Existing tasks without event-time history remain unknown, never inferred retroactively.
            $known = DB::table('risk_review_history')->where('app_id', $appId)
                ->where('version_name', ltrim(strtolower(trim($version)), 'v'))->where('market_channel', strtolower($channel))->exists();

            if ($known) {
                return 'unknown';
            }
            // Before the first capture, a configured review attempt is insufficient for a safe release.
            $configured = DB::table('app_version_plan_tasks as t')->join('app_version_plans as p', 'p.id', '=', 't.plan_id')
                ->where('p.app_id', $appId)->where('t.market_channel', $channel)->exists();

            return $configured ? 'unknown' : 'none';
        }
        if (isset($history[1]) && $history[0]->submitted_at === $history[1]->submitted_at) {
            return 'unknown';
        }
        $task = $history[0];
        if (substr((string) $task->submitted_at, 11) === '00:00:00') {
            return 'unknown';
        }
        if ($task->status === '审核中') {
            if ($persist) {
                DB::table('risk_hard_blocks')->insertOrIgnore([
                    'app_id' => $appId, 'device_identity' => $device, 'kind' => 'reviewing_visit',
                    'evidence_key' => hash('sha256', $appId.':'.$device.':'.$task->id),
                    'evidence' => json_encode(['history_id' => $task->id, 'attempt_key' => $task->attempt_key,
                        'version' => $version, 'channel' => $channel, 'status' => $task->status]),
                    'occurred_at' => $at->toDateTimeString(),
                ]);
            }

            return 'blocked';
        }

        // Post-listing evidence cannot clear earlier hard blocks. Submitted is not reviewing.
        return $task->status === '已上架' && $task->listed_at && $at->toDateTimeString() >= $task->listed_at ? 'clear' : 'unknown';
    }

    public function locked(int $appId, string $device): bool
    {
        return DB::table('risk_hard_blocks')->where('app_id', $appId)->where('device_identity', $device)->whereNull('invalidated_at')->exists();
    }
}
