<?php

namespace Tests\Unit;

use App\Models\RiskProbeLog;
use App\Services\Risk\RiskLedgerService;
use App\Services\Risk\RiskReleaseEngine;
use App\Services\Risk\RiskReviewEvidence;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectionRiskRepairTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        // Portable queue contract tests use SQLite; the production risk connection is Redis.
        config(['queue.connections.risk' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'risk_jobs', 'queue' => 'risk', 'retry_after' => 120, 'after_commit' => true]]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_28_120000_create_risk_ledger_tables.php'))->up();
        (require database_path('migrations/2026_10_30_160000_complete_risk_integration.php'))->up();
        (require database_path('migrations/2026_10_30_170000_isolate_historical_risk_scopes.php'))->up();
        (require database_path('migrations/2026_10_30_180000_async_risk_activity_projection.php'))->up();
        (require database_path('migrations/2026_11_19_160000_add_risk_review_stages.php'))->up();
        Schema::create('risk_probe_logs', function (Blueprint $t) {
            $t->id();
            foreach (array_unique(array_merge(RiskProbeLog::adminListColumns(), (new RiskProbeLog)->getFillable(), ['updated_at', 'route', 'probe_json', 'device_identity', 'touch_sample_count', 'click_sample_count', 'swipe_sample_count'])) as $name) {
                if ($name !== 'id') {
                    $t->string($name)->nullable();
                }
            }
        });
        Schema::create('system_apps', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('is_del')->default(0);
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->integer('app_id');
            foreach (['uuid', 'phone', 'account'] as $f) {
                $t->string($f)->nullable();
            } $t->integer('is_del')->default(0);
        });
        Schema::create('risk_lists', function (Blueprint $t) {
            $t->id();
            $t->integer('app_id');
            $t->integer('status');
            foreach (['decision', 'target_type', 'target_value'] as $f) {
                $t->string($f);
            }
        });
        Schema::create('app_version_plans', function (Blueprint $t) {
            $t->id();
            $t->integer('app_id');
        });
        Schema::create('app_version_plan_tasks', function (Blueprint $t) {
            $t->id();
            $t->integer('plan_id');
            foreach (['version', 'market_channel', 'submitted_at', 'listed_at', 'status'] as $f) {
                $t->string($f)->nullable();
            }
        });
        $review = \Mockery::mock(RiskReviewEvidence::class)->makePartial();
        $review->shouldReceive('observe')->andReturn('none');
        $this->app->instance(RiskReviewEvidence::class, $review);
        $engine = \Mockery::mock(RiskReleaseEngine::class);
        $engine->shouldReceive('blockers')->andReturn([]);
        $this->app->instance(RiskReleaseEngine::class, $engine);
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:30:00', 'Asia/Shanghai'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fixture(string $device = 'projection-device', array $reasons = ['screen_projection']): array
    {
        $log = RiskProbeLog::query()->create(['app_id' => 1, 'status' => 'ok', 'risk_score' => 70,
            'risk_reasons' => $reasons, 'validation_errors' => [], 'compliance_mode' => 1, 'ad_switch' => 0,
            'user_uuid' => $device, 'device_sn' => $device.'-install', 'client_ip' => '192.0.2.1',
            'app_version' => '1.0', 'market_channel' => 'huawei',
            'probe_json' => ['screen_projection_active' => true, 'env_allows_ads' => false]]);
        $id = DB::table('risk_scopes')->insertGetId(['app_id' => 1, 'ip' => '192.0.2.1', 'device_identity' => $device,
            'install_id' => $device.'-install', 'reason_codes' => json_encode($reasons), 'risk_score' => 70,
            'risk_level' => 'high', 'decision' => 'block', 'collected_at' => $log->created_at, 'source_log_id' => $log->id,
            'version_name' => '1.0', 'market_channel' => 'huawei', 'policy_eligible' => true]);
        DB::table('risk_timeline')->insert(['ip' => '192.0.2.1', 'scope_id' => $id, 'event_type' => 'intake',
            'source_log_id' => $log->id, 'reason_codes' => json_encode($reasons), 'occurred_at' => $log->created_at]);
        foreach ($reasons as $reason) {
            DB::table('risk_reason_evidence')->insert(['scope_id' => $id, 'code' => $reason, 'ip' => '192.0.2.1',
                'app_id' => 1, 'device_identity' => $device, 'hit_count' => 1, 'first_at' => $log->created_at, 'last_at' => $log->created_at]);
        }
        DB::table('risk_ledger_applied')->insert(['source_log_id' => $log->id, 'applied_at' => now()]);
        DB::table('risk_ledger_meta')->where('id', 1)->update(['cursor_id' => $log->id]);
        app(RiskLedgerService::class)->refreshDossier('192.0.2.1');

        return [$id, $log];
    }

    private function repair(bool $apply = true): array
    {
        return app(\App\Services\Risk\ProjectionRiskRepair::class)->run($apply, 0, 0, 200, 10);
    }

    public function test_command_automatically_walks_all_batches_and_reports_totals(): void
    {
        foreach (['first', 'second'] as $device) {
            [, $log] = $this->fixture($device, ['vpn']);
            $log->update(['probe_json' => ['is_vpn' => true]]);
        }
        $options = ['--paused-signals' => true, '--all' => true, '--limit' => 1];

        $this->assertSame(0, Artisan::call('app:risk-repair-projection', $options));
        $preview = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $preview['batches']);
        $this->assertSame(2, $preview['eligible']);
        $this->assertSame(0, $preview['released']);
        $this->assertSame(0, DB::table('risk_releases')->count());

        $this->assertSame(0, Artisan::call('app:risk-repair-projection', $options + ['--apply' => true]));
        $applied = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($applied['complete']);
        $this->assertSame(2, $applied['released']);
        $this->assertSame(2, DB::table('risk_releases')->count());
        $this->assertSame(0, Artisan::call('app:risk-repair-projection', $options + ['--apply' => true]));
        $this->assertSame(0, json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['released']);
    }

    public function test_auto_command_reports_incomplete_when_the_ledger_is_behind(): void
    {
        [, $log] = $this->fixture();
        $log->replicate()->save();

        $this->assertSame(1, Artisan::call('app:risk-repair-projection', ['--all' => true, '--apply' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"complete": false', $output);
        $this->assertStringContainsString('"inbox_pending": 1', $output);
        $this->assertSame(0, DB::table('risk_releases')->count());
    }

    public function test_dry_run_is_read_only_and_apply_is_idempotent_with_original_logs_unchanged(): void
    {
        [$id, $log] = $this->fixture();
        $original = (array) DB::table('risk_probe_logs')->where('id', $log->id)->first();
        $this->assertSame(1, $this->repair(false)['eligible']);
        $this->assertSame('active', DB::table('risk_scopes')->value('state'));
        $this->assertSame(0, DB::table('risk_releases')->count());
        $this->assertSame(1, $this->repair()['released']);
        $scope = DB::table('risk_scopes')->where('id', $id)->first();
        $this->assertSame('released', $scope->state);
        $this->assertSame(0, $scope->risk_score);
        $this->assertNull($scope->basis_hash);
        $this->assertSame($original, (array) DB::table('risk_probe_logs')->where('id', $log->id)->first());
        $this->assertSame('released', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertSame(0, $this->repair()['released']);
        $this->assertSame(1, DB::table('risk_releases')->count());
        $evidence = json_decode(DB::table('risk_releases')->value('evidence'), true);
        $this->assertSame(70, $evidence['before']['risk_score']);
        $this->assertSame([$log->id], $evidence['source_ids']);
        $this->expectException(\App\Exceptions\AdminException::class);
        (new RiskReleaseEngine(app(RiskLedgerService::class)))->revoke((int) $scope->release_id, 'retired-cannot-restore', 1, 1, 'test');
    }

    public function test_other_risks_are_not_released_and_shared_ip_stays_active(): void
    {
        [$pure] = $this->fixture('pure');
        [$mixed] = $this->fixture('mixed', ['screen_projection', 'drm_failed']);
        $result = $this->repair();
        $this->assertSame(1, $result['released']);
        $this->assertSame(1, $result['skipped']['other_risk']);
        $this->assertSame('active', DB::table('risk_scopes')->where('id', $mixed)->value('state'));
        $this->assertSame(70, DB::table('risk_scopes')->where('id', $mixed)->value('risk_score'));
        $this->assertSame('high', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertSame(0, DB::table('risk_releases')->value('ip_fully_released'));
    }

    public function test_paused_weight_repair_releases_only_supported_scopes_and_keeps_remaining_ip_risk(): void
    {
        [$paused, $log] = $this->fixture('paused', ['vpn', 'env_blocks_ads']);
        $log->update(['probe_json' => ['is_vpn' => true, 'env_allows_ads' => false]]);
        [$mixed, $mixedLog] = $this->fixture('mixed', ['sensor_static', 'hook']);
        $mixedLog->update(['probe_json' => ['sensor_static_score' => 90, 'hook_suspect' => true]]);
        $repair = app(\App\Services\Risk\ProjectionRiskRepair::class);
        $this->assertSame(1, $repair->run(false, 0, 0, 200, 10, true)['eligible']);
        $this->assertSame(1, $repair->run(true, 0, 0, 200, 10, true)['released']);
        $this->assertSame('released', DB::table('risk_scopes')->where('id', $paused)->value('state'));
        $this->assertSame('active', DB::table('risk_scopes')->where('id', $mixed)->value('state'));
        $this->assertSame('high', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertSame(70, (int) $log->fresh()->risk_score);
        $details = app(\App\Services\Risk\RiskDossierService::class)->scoreDetails(\App\Services\Risk\Ledger\IpNormalizer::idFor('192.0.2.1'));
        $this->assertSame(70, $details['score']);
        $this->assertSame(80, $details['raw_score']);
        $this->assertSame(['hook' => 80], array_column($details['items'], 'points', 'code'));
        $this->assertTrue($details['recalculated']);
        $this->assertTrue($details['reference_only']);
    }

    public function test_mixed_history_keeps_emulator_in_the_list_and_uses_the_recalculated_score(): void
    {
        [$id, $log] = $this->fixture('emulated', ['emulator', 'sensor_static', 'env_blocks_ads']);
        $log->update(['risk_score' => 75, 'probe_json' => ['is_emulator' => true, 'sensor_static_score' => 90, 'env_allows_ads' => false]]);
        DB::table('risk_scopes')->where('id', $id)->update(['risk_score' => 75]);
        $original = $log->fresh()->getRawOriginal();
        $repair = app(\App\Services\Risk\ProjectionRiskRepair::class);
        $this->assertSame(1, $repair->run(false, 0, 0, 200, 10, true)['rescore_eligible']);
        $this->assertSame(75, DB::table('risk_scopes')->value('risk_score'));
        $this->assertSame(1, $repair->run(true, 0, 0, 200, 10, true)['rescored']);
        $scope = DB::table('risk_scopes')->find($id);
        $this->assertSame('active', $scope->state);
        $this->assertSame('limit', $scope->decision);
        $this->assertSame(50, $scope->risk_score);
        $this->assertSame($original, $log->fresh()->getRawOriginal());
        $dossiers = app(\App\Services\Risk\RiskDossierService::class);
        $this->assertSame(50, $dossiers->list(['include_options' => false], 1, 1)['list'][0]['risk_score']);
        $details = $dossiers->scoreDetails(\App\Services\Risk\Ledger\IpNormalizer::idFor('192.0.2.1'));
        $this->assertSame(50, $details['raw_score']);
        $this->assertFalse($details['reference_only']);
        $this->assertSame(0, $repair->run(true, 0, 0, 200, 10, true)['rescored']);
    }

    public function test_source_reassessment_catches_hidden_other_signal_even_if_tags_were_lost(): void
    {
        [$id, $log] = $this->fixture();
        $log->probe_json = ['screen_projection_active' => true, 'root_suspect' => true];
        $log->save();
        $this->assertSame(0, $this->repair()['released']);
        $this->assertSame('active', DB::table('risk_scopes')->where('id', $id)->value('state'));
    }

    public function test_mixed_history_rescore_preserves_an_independent_blacklist_decision(): void
    {
        [$id, $log] = $this->fixture('listed', ['emulator', 'sensor_static']);
        $log->update(['probe_json' => ['is_emulator' => true, 'sensor_static_score' => 90]]);
        DB::table('risk_lists')->insert(['app_id' => 1, 'status' => 1, 'target_type' => 'device_identity', 'target_value' => 'listed', 'decision' => 'block']);

        $report = app(\App\Services\Risk\ProjectionRiskRepair::class)->run(true, 0, 0, 200, 10, true);

        $this->assertSame(1, $report['rescored']);
        $this->assertSame(0, $report['released']);
        $scope = DB::table('risk_scopes')->find($id);
        $this->assertSame(50, $scope->risk_score);
        $this->assertSame('block', $scope->decision);
        $this->assertSame('active', $scope->state);
    }

    public function test_missing_evidence_hard_blocks_and_manual_lists_are_not_released(): void
    {
        [$id, $log] = $this->fixture();
        DB::table('risk_lists')->insert(['app_id' => 1, 'status' => 1, 'target_type' => 'device_identity', 'target_value' => 'projection-device', 'decision' => 'block']);
        $this->assertSame(1, $this->repair()['skipped']['manual_list']);
        DB::table('risk_lists')->delete();
        DB::table('risk_hard_blocks')->insert(['app_id' => 1, 'device_identity' => 'projection-device', 'kind' => 'reviewing_visit', 'evidence_key' => 'hard', 'evidence' => '{}', 'occurred_at' => now()]);
        $this->assertSame(1, $this->repair()['skipped']['hard_block']);
        DB::table('risk_hard_blocks')->delete();
        DB::table('risk_probe_logs')->where('id', $log->id)->delete();
        $this->assertSame(0, $this->repair()['released']);
        $this->assertSame('active', DB::table('risk_scopes')->where('id', $id)->value('state'));
    }

    public function test_old_queued_projection_does_not_recollect_but_new_other_risk_does(): void
    {
        [$id, $log] = $this->fixture();
        $this->assertSame(1, $this->repair()['released']);
        $old = $log->replicate();
        $old->save();
        $before = (array) DB::table('risk_probe_logs')->where('id', $old->id)->first();
        app(RiskLedgerService::class)->applyLog($old);
        $this->assertSame(0, DB::table('risk_scopes')->where('state', 'active')->count());
        $this->assertSame($before, (array) DB::table('risk_probe_logs')->where('id', $old->id)->first());
        $other = $old->replicate();
        $other->risk_reasons = ['hook'];
        $other->probe_json = ['hook_suspect' => true];
        $other->risk_score = 80;
        $other->save();
        app(RiskLedgerService::class)->applyLog($other);
        $this->assertSame(1, DB::table('risk_scopes')->where('state', 'active')->count());
    }
}
