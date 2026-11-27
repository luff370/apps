<?php

namespace Tests\Unit;

use App\Models\RiskProbeLog;
use App\Services\Risk\ActivityEventService;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskLedgerService;
use App\Services\Risk\RiskPolicyService;
use App\Services\Risk\RiskReleaseEngine;
use App\Services\Risk\RiskReviewEvidence;
use App\Support\Utils\Token;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskIntegrationCompletionTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:30:00', 'Asia/Shanghai'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function collect(): object
    {
        $decision = ['score' => 80, 'reasons' => ['device_env_digest_mismatch'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $log = RiskProbeLog::query()->create(['app_id' => 1, 'status' => 'ok', 'risk_score' => 80, 'risk_reasons' => ['device_env_digest_mismatch'],
            'compliance_mode' => 1, 'ad_switch' => 0, 'user_uuid' => 'device-a', 'device_sn' => 'install-a',
            'app_version' => '1.0', 'market_channel' => 'huawei', 'client_ip' => '192.0.2.1', 'route' => 'api/report',
            'created_at' => '2026-10-30 10:00:00', 'probe_json' => ['_risk_basis' => ['hash' => RiskPolicyService::basis($decision)], '_identity_kind' => 'device']]);
        $log->created_at = '2026-10-30 10:00:00';
        $log->save();
        app(RiskLedgerService::class)->applyLog($log);

        return DB::table('risk_scopes')->first();
    }

    public function test_protocol_mismatch_does_not_recollect_on_another_ip_under_an_existing_disposition(): void
    {
        $this->collect();
        $raw = ['score' => 5, 'reasons' => ['native_probe_protocol_mismatch'], 'compliance_mode' => 0, 'ad_switch' => 1];
        $effective = app(RiskPolicyService::class)->overlay($raw, 1, 'device-a', []);
        $this->assertSame(1, $effective['compliance_mode']);

        $log = RiskProbeLog::query()->first()->replicate();
        $log->client_ip = '192.0.2.2';
        $log->risk_reasons = $effective['reasons'];
        $log->probe_json = ['_risk_basis' => ['decision' => $raw]];
        $log->save();
        app(RiskLedgerService::class)->applyLog($log);

        $this->assertSame(1, DB::table('risk_scopes')->count());
        $this->assertSame('device_env_digest_mismatch', DB::table('risk_reason_evidence')->value('code'));
        $this->assertSame(['native_probe_protocol_mismatch'], $log->fresh()->risk_reasons);
    }

    public function test_zero_score_probe_with_a_manual_block_does_not_create_a_scored_dossier(): void
    {
        $log = RiskProbeLog::query()->create([
            'app_id' => 1, 'status' => 'ok', 'risk_score' => 0, 'risk_reasons' => ['vpn', 'list_blacklist'],
            'compliance_mode' => 1, 'ad_switch' => 0, 'user_uuid' => 'device-a', 'client_ip' => '192.0.2.1',
            'probe_json' => ['is_vpn' => true, '_risk_basis' => ['decision' => [
                'score' => 0, 'reasons' => ['vpn'], 'compliance_mode' => 0, 'ad_switch' => 1,
                'score_items' => [['code' => 'vpn', 'points' => 0]],
            ]]],
        ]);

        app(RiskLedgerService::class)->applyLog($log);

        $this->assertSame(0, DB::table('risk_scopes')->count());
        $this->assertSame(0, DB::table('risk_ip_dossiers')->count());
        $this->assertSame(['vpn', 'list_blacklist'], $log->fresh()->risk_reasons);
        $this->assertSame(1, (int) $log->fresh()->compliance_mode);
    }

    public function test_historical_scopes_are_statistics_only_and_new_hits_create_an_independent_policy_scope(): void
    {
        $old = $this->collect();
        DB::table('risk_scopes')->where('id', $old->id)->update(['policy_eligible' => false]);
        DB::table('risk_ledger_meta')->where('id', 1)->update(['enforcement_source_id' => $old->source_log_id]);
        $clean = ['score' => 0, 'reasons' => [], 'compliance_mode' => 0, 'ad_switch' => 1];
        $this->assertSame($clean, app(RiskPolicyService::class)->overlay($clean, 1, 'device-a', []));
        $this->assertNull(app(RiskReleaseEngine::class)->tryRelease(DB::table('risk_scopes')->first()));
        $log = RiskProbeLog::query()->first()->replicate();
        $log->created_at = Carbon::now();
        $log->save();
        app(RiskLedgerService::class)->applyLog($log);
        $this->assertSame(2, DB::table('risk_scopes')->count());
        $this->assertSame(1, DB::table('risk_scopes')->where('policy_eligible', true)->count());
        $effective = app(RiskPolicyService::class)->overlay($clean, 1, 'device-a', []);
        $this->assertSame(1, $effective['compliance_mode']);
        $this->assertSame(0, $effective['ad_switch']);
    }

    private function request(array $events, bool $signed = true): Request
    {
        $r = Request::create('/api/risk/activity-events/batch', 'POST', ['events' => $events], [], [],
            ['HTTP_APP_ID' => '1', 'HTTP_UUID' => 'device-a', 'HTTP_DEVICE_SN' => 'install-a', 'HTTP_APP_VERSION' => '1.0', 'HTTP_MARKET_CHANNEL' => 'huawei', 'REMOTE_ADDR' => '192.0.2.1']);
        if ($signed) {
            $r->attributes->set('device_env_risk', ['status' => 'ok', 'score' => 0, 'decision' => ['compliance_mode' => 0, 'ad_switch' => 1]]);
        }
        $this->app->instance('request', $r);

        return $r;
    }

    public function test_single_historical_batch_never_satisfies_live_usage_even_with_current_last_interaction(): void
    {
        $this->collect();
        $events = [];
        foreach (range(0, 61) as $i) {
            $events[] = ['event_id' => 'bulk-'.$i, 'session_id' => 's', 'event_type' => 'interaction', 'occurred_at' => Carbon::now()->timestamp - 1830 + $i * 30,
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 30000, 'foreground' => true, 'screen_interactive' => true, 'interaction' => true];
        }
        $result = app(ActivityEventService::class)->ingest($this->request($events));
        $this->assertSame(62, $result['accepted']);
        $this->assertSame(62, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertSame(0, DB::table('risk_releases')->count());
        $this->assertSame('active', DB::table('risk_scopes')->value('state'));
        $this->assertLessThan(10, DB::table('risk_activity_events')->where('online_verified', 1)->count());
    }

    public function test_review_evidence_survives_task_replacement_and_unbounded_later_activity(): void
    {
        DB::table('app_version_plans')->insert(['id' => 1, 'app_id' => 1]);
        DB::table('app_version_plan_tasks')->insert(['id' => 1, 'plan_id' => 1, 'version' => '1.0', 'market_channel' => 'huawei', 'submitted_at' => '2026-10-30 09:00:00', 'status' => '审核中']);
        Carbon::setTestNow(Carbon::parse('2026-10-30 09:30:00', 'Asia/Shanghai'));
        app(RiskReviewEvidence::class)->captureApp(1);
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:30:00', 'Asia/Shanghai'));
        $scope = $this->collect();
        $this->assertSame(1, DB::table('risk_hard_blocks')->count());
        DB::table('app_version_plan_tasks')->delete();
        DB::table('app_version_plan_tasks')->insert(['id' => 2, 'plan_id' => 1, 'version' => '1.0', 'market_channel' => 'huawei', 'submitted_at' => '2026-10-30 09:00:00', 'listed_at' => '2026-10-30 11:00:00', 'status' => '已上架']);
        app(RiskReviewEvidence::class)->captureApp(1);
        foreach (range(1, 201) as $i) {
            DB::table('risk_probe_logs')->insert(['app_id' => 1, 'user_uuid' => 'device-a', 'status' => 'ok', 'created_at' => '2026-10-30 12:00:00']);
        }
        $blocks = app(RiskReleaseEngine::class)->blockers($scope);
        $this->assertTrue($blocks[0]['prevent']);
        $this->assertSame('reviewing_visit', $blocks[0]['type']);
    }

    public function test_actual_online_usage_releases_same_evidence_new_risk_recollects_and_revoke_restores_policy(): void
    {
        $scope = $this->collect();
        DB::table('risk_scopes')->where('id', $scope->id)->update(['reason_codes' => '["device_env_digest_mismatch","vpn","imu_static_during_activity"]']);
        foreach (range(0, 11) as $i) {
            $at = Carbon::now()->subSeconds(1980 - $i * 180)->toDateTimeString();
            DB::table('risk_activity_events')->insert(['event_id' => 'live-'.$i, 'session_id' => 'live', 'app_id' => 1, 'device_identity' => 'device-a',
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 180000, 'online_verified' => 1, 'projected_at' => now(),
                'foreground' => 1, 'screen_interactive' => 1, 'interaction' => (int) ($i % 3 === 0 || $i === 11), 'occurred_at' => $at, 'received_at' => $at]);
        }
        $downgrade = app(RiskReleaseEngine::class)->tryRelease($scope);
        $this->assertTrue($downgrade['downgraded']);
        $this->assertSame(0, DB::table('risk_releases')->count());
        $engine = app(RiskReleaseEngine::class);
        $this->assertNull($engine->tryRelease($scope));

        // A retry of the first evaluation cannot advance the second stage.
        $this->assertSame(0, DB::table('risk_releases')->count());
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $at = Carbon::now()->toDateTimeString();
        DB::table('risk_activity_events')->insert(['event_id' => 'live-after-review', 'session_id' => 'live', 'app_id' => 1, 'device_identity' => 'device-a',
            'event_seq' => 13, 'monotonic_elapsed_ms' => 2010000, 'online_verified' => 1, 'projected_at' => now(),
            'foreground' => 1, 'screen_interactive' => 1, 'interaction' => 1, 'occurred_at' => $at, 'received_at' => $at]);
        $confirmed = $engine->tryRelease($scope);
        $this->assertSame('released', $confirmed['state']);
        $this->assertSame('continuous-use-release', DB::table('risk_releases')->value('rule_id'));
        $this->assertSame('5', DB::table('risk_releases')->value('rule_version'));
        $this->assertSame('auto_release', DB::table('risk_timeline')->whereNotNull('release_id')->value('event_type'));
        $this->assertNull($engine->tryRelease($scope));
        $release = ['id' => $confirmed['release_id']];
        $base = ['score' => 80, 'reasons' => ['device_env_digest_mismatch', 'drm_failed'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $policy = app(RiskPolicyService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(0, $policy->overlay($base, 1, 'device-a', ['app_version' => '1.0', 'market_channel' => 'huawei'])['compliance_mode']);
        $policyQueries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($policyQueries as $query) {
            $this->assertStringStartsWith('select ', strtolower($query['query']));
        }
        $new = ['score' => 90, 'reasons' => ['root'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $this->assertSame($new, $policy->overlay($new, 1, 'device-a', ['app_version' => '1.0', 'market_channel' => 'huawei']));
        $result = app(RiskReleaseEngine::class)->revoke($release['id'], 'same-request', 1, 1, 'admin');
        $this->assertSame('revoked', $result['status']);
        $this->assertSame(0, $policy->overlay(['score' => 0, 'reasons' => [], 'compliance_mode' => 0, 'ad_switch' => 1], 1, 'device-a', [])['compliance_mode']);
        $this->assertSame('restored', app(RiskReleaseEngine::class)->revoke($release['id'], 'same-request', 1, 1, 'admin')['result']);
        $this->assertNull(app(RiskReleaseEngine::class)->tryRelease(DB::table('risk_scopes')->first()));
    }

    public function test_mixed_reasons_and_hard_blocks_keep_observation_out_of_automatic_safe_release(): void
    {
        $scope = $this->collect();
        DB::table('risk_scopes')->where('id', $scope->id)->update(['review_stage' => 'observation',
            'reviewed_at' => now()->subHour(), 'review_reasons' => json_encode(['device_env_digest_mismatch', 'hook'])]);
        foreach (range(0, 61) as $i) {
            $at = Carbon::now()->subSeconds(1830 - $i * 30)->toDateTimeString();
            DB::table('risk_activity_events')->insert(['event_id' => 'mixed-'.$i, 'session_id' => 'mixed', 'app_id' => 1, 'device_identity' => 'device-a',
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 30000, 'online_verified' => 1, 'projected_at' => now(),
                'foreground' => 1, 'screen_interactive' => 1, 'interaction' => 1, 'occurred_at' => $at, 'received_at' => $at]);
        }
        $engine = app(RiskReleaseEngine::class);
        $this->assertNull($engine->tryRelease($scope));
        $this->assertSame('manual_confirmation_required', DB::table('risk_scopes')->value('last_skip_reason'));
        DB::table('risk_scopes')->where('id', $scope->id)->update(['review_reasons' => json_encode(['device_env_digest_mismatch', 'native_probe_protocol_mismatch'])]);
        DB::table('risk_hard_blocks')->insert(['app_id' => 1, 'device_identity' => 'device-a', 'kind' => 'reviewing_visit',
            'evidence_key' => 'observe-hard', 'evidence' => '{}', 'occurred_at' => now()]);
        $this->assertNull($engine->tryRelease($scope));
        $this->assertSame('reviewing_visit', DB::table('risk_scopes')->value('last_skip_reason'));
        $this->assertSame(0, DB::table('risk_releases')->count());
    }

    public function test_paused_reasons_alone_do_not_qualify_for_timed_release(): void
    {
        $workflow = app(\App\Services\Risk\RiskReviewWorkflow::class);
        $scope = (object) ['reviewed_at' => now(), 'review_reasons' => '["vpn","sensor_static"]', 'reason_codes' => '["env_blocks_ads","drm_failed"]'];

        $this->assertFalse($workflow->canReleaseAutomatically($scope));
    }

    public function test_qualified_online_usage_still_enters_observation_after_the_worker_is_late(): void
    {
        $scope = $this->collect();
        foreach (range(0, 61) as $i) {
            $at = Carbon::now()->subSeconds(1830 - $i * 30);
            DB::table('risk_activity_events')->insert(['event_id' => 'late-'.$i, 'session_id' => 'late', 'app_id' => 1, 'device_identity' => 'device-a',
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 30000, 'online_verified' => 1, 'projected_at' => now(),
                'foreground' => 1, 'screen_interactive' => 1, 'interaction' => 1, 'occurred_at' => $at->toDateTimeString(), 'received_at' => $at->toDateTimeString()]);
        }
        Carbon::setTestNow(Carbon::now()->addMinutes(10));

        $release = app(RiskReleaseEngine::class)->tryRelease($scope);

        $this->assertTrue($release['downgraded']);
        $this->assertSame('observation', DB::table('risk_scopes')->value('review_stage'));
        $this->assertSame('active', DB::table('risk_scopes')->value('state'));
    }

    public function test_manual_ip_review_requires_two_explicit_confirmations_and_retries_do_not_advance_twice(): void
    {
        $this->collect();
        $engine = app(RiskReleaseEngine::class);
        $first = ['request_id' => 'manual-lower-1', 'expected_state' => 'high', 'expected_version' => 1];
        $result = $engine->removeIp('192.0.2.1', 7, '运营甲', $first);
        $this->assertSame('watch', $result['state']);
        $this->assertSame(0, DB::table('risk_releases')->count());
        $this->assertSame($result, $engine->removeIp('192.0.2.1', 7, '运营甲', $first));
        $this->assertSame('observation', DB::table('risk_scopes')->value('review_stage'));
        $second = ['request_id' => 'manual-confirm-2', 'expected_state' => 'watch', 'expected_version' => 2];
        $safe = $engine->removeIp('192.0.2.1', 7, '运营甲', $second);
        $this->assertSame('released', $safe['state']);
        $this->assertSame('safe', DB::table('risk_scopes')->value('review_stage'));
        $this->assertSame('manual-reviewed-safe', DB::table('risk_releases')->value('rule_id'));
        $this->assertSame('低风险确认进入安全区', DB::table('risk_timeline')->where('event_type', 'manual_release')->value('title'));
        $base = ['score' => 80, 'reasons' => ['device_env_digest_mismatch'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $this->assertSame(0, app(RiskPolicyService::class)->overlay($base, 1, 'device-a', [])['compliance_mode']);
        $this->assertSame($safe, $engine->removeIp('192.0.2.1', 7, '运营甲', $second));
        $this->assertSame(1, DB::table('risk_releases')->count());
    }

    public function test_reviewed_subset_and_version_change_do_not_recollect_but_new_risk_escalates(): void
    {
        $base = ['score' => 60, 'reasons' => ['touch_biometrics_synthetic', 'native_probe_protocol_mismatch'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $log = RiskProbeLog::query()->create(['app_id' => 1, 'status' => 'ok', 'risk_score' => 60,
            'risk_reasons' => $base['reasons'], 'compliance_mode' => 1, 'ad_switch' => 0,
            'user_uuid' => 'device-a', 'device_sn' => 'install-a', 'client_ip' => '192.0.2.1',
            'app_version' => '2.6.3', 'market_channel' => 'huawei', 'probe_json' => ['_risk_basis' => ['decision' => $base, 'hash' => RiskPolicyService::basis($base)]]]);
        $ledger = app(RiskLedgerService::class);
        $ledger->applyLog($log);
        $engine = app(RiskReleaseEngine::class);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'subset-lower', 'expected_state' => 'high', 'expected_version' => 1]);
        $subset = ['score' => 0, 'reasons' => ['native_probe_protocol_mismatch'], 'compliance_mode' => 0, 'ad_switch' => 1];
        $policy = app(RiskPolicyService::class);
        $effective = $policy->overlay($subset, 1, 'device-a', ['app_version' => '2.6.4', 'market_channel' => 'huawei']);
        $this->assertSame(0, $effective['compliance_mode']);
        $this->assertSame(1, $effective['ad_switch']);
        $incoming = $log->replicate();
        $incoming->app_version = '2.6.4';
        $incoming->risk_reasons = $subset['reasons'];
        $incoming->probe_json = ['_risk_basis' => ['decision' => $subset, 'hash' => RiskPolicyService::basis($subset)]];
        $incoming->save();
        $ledger->applyLog($incoming);
        $this->assertSame(1, DB::table('risk_scopes')->count());
        $this->assertSame(0, DB::table('risk_timeline')->where('event_type', 'reintake')->count());
        $this->assertSame('watch', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertSame(60, (int) RiskProbeLog::query()->where('id', $incoming->id)->value('risk_score'));
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'subset-safe', 'expected_state' => 'watch', 'expected_version' => 2]);
        $this->assertSame(0, $policy->overlay($subset, 1, 'device-a', ['app_version' => '2.6.4'])['score']);
        $known = $incoming->replicate();
        $known->save();
        $ledger->applyLog($known);
        $this->assertSame(0, DB::table('risk_scopes')->where('state', 'active')->count());
        $novel = ['score' => 80, 'reasons' => ['hook'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $this->assertSame($novel, $policy->overlay($novel, 1, 'device-a', []));
        $new = $known->replicate();
        $new->risk_score = 80;
        $new->risk_reasons = ['hook'];
        $new->probe_json = ['_risk_basis' => ['decision' => $novel, 'hash' => RiskPolicyService::basis($novel)]];
        $new->save();
        $ledger->applyLog($new);
        $this->assertSame(1, DB::table('risk_scopes')->where('state', 'active')->where('review_stage', 'enforced')->count());
        $this->assertSame('high', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertSame(80, $policy->overlay($subset, 1, 'device-a', [])['score']);
    }

    public function test_a_new_request_with_stale_state_cannot_accidentally_confirm_twice(): void
    {
        $this->collect();
        $engine = app(RiskReleaseEngine::class);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'first-lower', 'expected_state' => 'high', 'expected_version' => 1]);
        try {
            $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'different-request-stale-page', 'expected_state' => 'high', 'expected_version' => 1]);
            $this->fail('Stale confirmation must not enter the safe area');
        } catch (\App\Exceptions\AdminException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertSame('observation', DB::table('risk_scopes')->value('review_stage'));
        $this->assertSame(0, DB::table('risk_releases')->count());
        $this->assertSame(1, DB::table('risk_review_actions')->count());
    }

    public function test_new_risk_during_observation_requires_fresh_usage_and_invalidates_stale_safe_confirmation(): void
    {
        $scope = $this->collect();
        $engine = app(RiskReleaseEngine::class);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'observe-before-new-risk', 'expected_state' => 'high', 'expected_version' => 1]);
        $source = RiskProbeLog::query()->first()->replicate();
        $source->risk_reasons = ['hook'];
        $source->probe_json = ['_risk_basis' => ['decision' => ['score' => 80, 'reasons' => ['hook'], 'compliance_mode' => 1, 'ad_switch' => 0]]];
        $source->save();
        app(RiskLedgerService::class)->applyLog($source);
        $fresh = DB::table('risk_scopes')->where('id', $scope->id)->first();
        $this->assertSame('enforced', $fresh->review_stage);
        $this->assertNotNull($fresh->hold_at);
        $this->assertNull($fresh->evidence_after_hold_at);
        $this->assertNull($engine->tryRelease($fresh));
        $this->assertSame('same_rule_hold', DB::table('risk_scopes')->where('id', $scope->id)->value('last_skip_reason'));
        $this->expectException(\App\Exceptions\AdminException::class);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'stale-safe-after-hook', 'expected_state' => 'watch', 'expected_version' => 2]);
    }

    public function test_a_new_reviewing_visit_overrides_a_previous_safe_confirmation_immediately(): void
    {
        $this->collect();
        $engine = app(RiskReleaseEngine::class);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'review-lower', 'expected_state' => 'high', 'expected_version' => 1]);
        $engine->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'review-safe', 'expected_state' => 'watch', 'expected_version' => 2]);
        DB::table('app_version_plans')->insert(['id' => 1, 'app_id' => 1]);
        DB::table('app_version_plan_tasks')->insert(['plan_id' => 1, 'version' => '1.0', 'market_channel' => 'huawei', 'submitted_at' => '2026-10-30 12:00:00', 'status' => '审核中']);
        app(RiskReviewEvidence::class)->captureApp(1);
        $raw = ['score' => 80, 'reasons' => ['device_env_digest_mismatch'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $this->assertSame(1, app(RiskPolicyService::class)->overlay($raw, 1, 'device-a', ['app_version' => '1.0', 'market_channel' => 'huawei'])['compliance_mode']);
        $source = RiskProbeLog::query()->first()->replicate();
        $source->created_at = Carbon::now();
        $source->save();
        app(RiskLedgerService::class)->applyLog($source);
        $this->assertSame(1, DB::table('risk_scopes')->where('state', 'active')->count());
        $this->assertSame('high', DB::table('risk_ip_dossiers')->value('state'));
        $this->assertTrue(app(RiskReviewEvidence::class)->locked(1, 'device-a'));
    }

    public function test_hard_review_blocks_prevent_manual_downgrade(): void
    {
        $this->collect();
        DB::table('risk_hard_blocks')->insert(['app_id' => 1, 'device_identity' => 'device-a', 'kind' => 'reviewing_visit',
            'evidence_key' => 'review-lock', 'evidence' => '{}', 'occurred_at' => now()]);
        $this->expectException(\App\Exceptions\AdminException::class);
        app(RiskReleaseEngine::class)->removeIp('192.0.2.1', 1, 'admin', ['request_id' => 'blocked-lower', 'expected_state' => 'high', 'expected_version' => 1]);
    }

    public function test_authenticated_activity_links_account_without_rewriting_intake_snapshot_and_populates_timeline(): void
    {
        $this->collect();
        DB::table('users')->insert(['id' => 123, 'app_id' => 1, 'uuid' => 'device-a', 'account' => 'real-account']);
        $token = Token::generate(['user_id' => 123]);
        $r = $this->request([['event_id' => 'login', 'session_id' => 's', 'event_type' => 'login', 'occurred_at' => Carbon::now()->timestamp,
            'event_seq' => 1, 'monotonic_elapsed_ms' => 0, 'foreground' => true, 'screen_interactive' => true, 'interaction' => true]]);
        $r->headers->set('Token', $token);
        $this->assertSame(1, app(ActivityEventService::class)->ingest($r)['accepted']);
        $this->assertSame(0, DB::table('risk_scope_accounts')->count());
        $this->assertSame(0, DB::table('risk_access_facts')->where('event_type', 'login')->count());
        $this->assertSame(1, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertSame(0, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertNull(DB::table('risk_intakes')->value('user_id'));
        $this->assertSame(1, DB::table('risk_scope_accounts')->count());
        $this->assertSame(1, DB::table('risk_ip_dossiers')->value('account_count'));
        $devices = app(\App\Services\Risk\RiskDossierService::class)->relations(\App\Services\Risk\Ledger\IpNormalizer::idFor('192.0.2.1'), 'devices', 1, 20);
        $this->assertSame(1, $devices['list'][0]['account_count']);
        $this->assertSame(1, DB::table('risk_timeline')->where('event_type', 'login')->count());
        $this->assertSame('123', DB::table('risk_access_facts')->where('event_type', 'login')->value('user_id'));
    }

    public function test_publishing_rejects_ambiguous_attempts_and_does_not_invent_review_windows_for_rejected_tasks(): void
    {
        DB::table('app_version_plans')->insert(['id' => 1, 'app_id' => 1]);
        $task = ['plan_id' => 1, 'version' => '1.0', 'market_channel' => 'huawei', 'submitted_at' => '2026-10-30 10:01:00', 'status' => '审核中'];
        DB::table('app_version_plan_tasks')->insert($task);
        DB::table('risk_access_facts')->insert(['event_key' => 'guard', 'app_id' => 1, 'device_identity' => 'd',
            'version_name' => '1.0', 'market_channel' => 'huawei', 'event_type' => 'guard', 'decision' => 'block',
            'occurred_at' => '2026-10-30 11:00:00', 'received_at' => '2026-10-30 11:00:00']);
        $query = app(\App\Services\Risk\PublishingQuery::class);
        $this->assertCount(1, $query->matched('2026-10-30', '2026-10-30')->get());
        DB::table('app_version_plan_tasks')->insert($task);
        $this->assertCount(0, $query->matched('2026-10-30', '2026-10-30')->get());
        DB::table('app_version_plan_tasks')->where('id', 2)->delete();
        DB::table('app_version_plan_tasks')->update(['status' => '被拒']);
        $this->assertCount(0, $query->matched('2026-10-30', '2026-10-30')->get());
    }

    public function test_test_account_match_is_app_scoped_and_hard_evidence_survives_configuration_removal(): void
    {
        DB::table('users')->insert(['id' => 123, 'app_id' => 1, 'uuid' => 'device-a', 'account' => 'tester']);
        DB::table('risk_monitor_accounts')->insert(['app_id' => 2, 'account' => 'tester', 'subject_name' => 'scope2', 'status' => 1]);
        $ledger = app(RiskLedgerService::class);
        $at = Carbon::now();
        $ledger->recordAccess('other-app', 1, 'device-a', '123', '192.0.2.1', '1.0', 'huawei', 'login', 'pass', $at, $at);
        $this->assertFalse(app(RiskReviewEvidence::class)->locked(1, 'device-a'));
        DB::table('risk_monitor_accounts')->insert(['app_id' => 1, 'account' => 'tester', 'subject_name' => 'scope1', 'status' => 1]);
        $ledger->recordAccess('own-app', 1, 'device-a', '123', '192.0.2.1', '1.0', 'huawei', 'login', 'pass', $at, $at);
        DB::table('risk_monitor_accounts')->delete();
        $this->assertTrue(app(RiskReviewEvidence::class)->locked(1, 'device-a'));
        $this->assertSame('test_account', DB::table('risk_hard_blocks')->value('kind'));
    }

    public function test_laravel_worker_consumes_probe_job_and_retries_are_idempotent(): void
    {
        $scope = $this->collect();
        $source = RiskProbeLog::query()->first()->replicate();
        $source->save();
        $before = DB::table('risk_reason_evidence')->sum('hit_count');
        $dispatcher = app(\App\Services\Risk\RiskWorkDispatcher::class);
        $this->assertTrue($dispatcher->probe((int) $source->id));
        $this->assertSame(1, DB::table('risk_jobs')->count());
        $this->assertFalse(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['connection' => 'risk', '--queue' => 'risk', '--once' => true, '--sleep' => 0, '--tries' => 1]);
        $this->assertSame(0, DB::table('risk_jobs')->count());
        $this->assertTrue(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
        $this->assertTrue(app(RiskLedgerService::class)->caughtUp());
        $after = DB::table('risk_reason_evidence')->sum('hit_count');
        $this->assertGreaterThan($before, $after);
        (new \App\Jobs\Risk\ProcessRiskProbe((int) $source->id))->handle(app(RiskLedgerService::class));
        $this->assertSame($after, DB::table('risk_reason_evidence')->sum('hit_count'));
    }

    public function test_business_audit_only_inserts_source_and_queue_envelope(): void
    {
        $request = $this->request([]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(\App\Support\Services\RiskProbeAuditService::class)->record($request, [
            'status' => 'ok', 'package_name' => 'example.app', 'app_id' => 1, 'probe' => [], 'validation' => [],
            'score' => 80, 'reasons' => ['device_env_digest_mismatch'], 'decision' => ['score' => 80, 'reasons' => ['device_env_digest_mismatch'], 'compliance_mode' => 1, 'ad_switch' => 0],
        ]);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('insert into "risk_probe_logs"', strtolower($queries[0]['query']));
        $this->assertStringContainsString('insert into "risk_jobs"', strtolower($queries[1]['query']));
        $this->assertSame(0, DB::table('risk_scopes')->count());
        $this->assertSame(0, DB::table('risk_ledger_applied')->count());
        $this->assertSame(1, DB::table('risk_jobs')->count());
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['connection' => 'risk', '--queue' => 'risk', '--once' => true, '--sleep' => 0, '--tries' => 1]);
        $this->assertSame(1, DB::table('risk_scopes')->count());
        $this->assertTrue(app(RiskLedgerService::class)->caughtUp());
    }

    public function test_activity_queue_projects_before_release_and_retains_online_usage_requirement(): void
    {
        $scope = $this->collect();
        foreach (range(0, 61) as $i) {
            $at = Carbon::now()->subSeconds(1830 - $i * 30)->toDateTimeString();
            DB::table('risk_activity_events')->insert(['event_id' => 'async-live-'.$i, 'session_id' => 'async-live', 'app_id' => 1, 'device_identity' => 'device-a',
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 30000, 'online_verified' => 1,
                'event_type' => 'interaction', 'decision' => 'pass', 'ip' => '192.0.2.1',
                'foreground' => 1, 'screen_interactive' => 1, 'interaction' => 1, 'occurred_at' => $at, 'received_at' => $at]);
        }
        $this->assertNull(app(RiskReleaseEngine::class)->tryRelease($scope));
        $this->assertSame('activity_projection_pending', DB::table('risk_scopes')->value('last_skip_reason'));
        $this->assertTrue(app(\App\Services\Risk\RiskWorkDispatcher::class)->activity());
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['connection' => 'risk', '--queue' => 'risk', '--once' => true, '--sleep' => 0, '--tries' => 1]);
        $this->assertSame(0, DB::table('risk_activity_events')->whereNull('projected_at')->count());
        $this->assertSame(0, DB::table('risk_releases')->count());
        $this->assertSame('observation', DB::table('risk_scopes')->value('review_stage'));
        $this->assertSame(0, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertSame(0, DB::table('risk_releases')->count());
    }

    public function test_failed_queue_publication_keeps_source_for_scheduled_recovery(): void
    {
        $this->collect();
        $source = RiskProbeLog::query()->first()->replicate();
        $source->save();
        \Illuminate\Support\Facades\Queue::shouldReceive('connection')->once()->with('risk')->andThrow(new \RuntimeException('queue unavailable'));
        $this->assertFalse(app(\App\Services\Risk\RiskWorkDispatcher::class)->probe((int) $source->id));
        $this->assertSame(2, RiskProbeLog::query()->count());
        $this->assertFalse(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
        app(RiskLedgerService::class)->sync(100, 1);
        $this->assertTrue(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
    }

    public function test_candidate_account_is_verified_in_consumer_and_wrong_app_login_is_downgraded(): void
    {
        DB::table('users')->insert(['id' => 456, 'app_id' => 2, 'uuid' => 'other-device', 'account' => 'other-app']);
        $this->collect();
        $source = RiskProbeLog::query()->first()->replicate();
        $probe = $source->probe_json;
        $probe['_candidate_user_id'] = 456;
        $probe['_user_id'] = '456';
        $probe['_access_event'] = ['id' => 'wrong-app-login', 'type' => 'login'];
        $source->probe_json = $probe;
        $source->save();
        (new \App\Jobs\Risk\ProcessRiskProbe((int) $source->id))->handle(app(RiskLedgerService::class));
        $fact = DB::table('risk_access_facts')->where('event_type', 'access')->first();
        $this->assertNotNull($fact);
        $this->assertNull($fact->user_id);
        $this->assertSame(0, DB::table('risk_scope_accounts')->count());
        $this->assertSame(0, DB::table('risk_access_facts')->where('event_type', 'login')->count());
    }

    public function test_sync_queue_is_refused_without_running_projection_in_business_request(): void
    {
        config(['risk.queue_connection' => 'sync']);
        $this->collect();
        $source = RiskProbeLog::query()->first()->replicate();
        $source->save();
        $this->assertFalse(app(\App\Services\Risk\RiskWorkDispatcher::class)->probe((int) $source->id));
        $this->assertFalse(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
        app(RiskLedgerService::class)->sync(100, 1);
        $this->assertTrue(DB::table('risk_ledger_applied')->where('source_log_id', $source->id)->exists());
    }

    public function test_activity_projection_failure_rolls_back_and_pending_source_can_retry(): void
    {
        $this->collect();
        $this->assertSame(1, app(ActivityEventService::class)->ingest($this->request([[
            'event_id' => 'retry-event', 'session_id' => 'retry', 'event_type' => 'access', 'occurred_at' => Carbon::now()->timestamp,
        ]]))['accepted']);
        $engine = \Mockery::mock(RiskReleaseEngine::class);
        $engine->shouldReceive('evaluateDevice')->once()->andThrow(new \RuntimeException('worker crash'));
        try {
            (new \App\Services\Risk\RiskActivityProjector(app(RiskLedgerService::class), $engine))->sync();
            $this->fail('Expected simulated worker failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('worker crash', $exception->getMessage());
        }
        $this->assertNull(DB::table('risk_activity_events')->value('projected_at'));
        $this->assertSame(0, DB::table('risk_access_facts')->where('event_type', 'access')->count());
        $this->assertSame(1, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertNotNull(DB::table('risk_activity_events')->value('projected_at'));
        $this->assertSame(1, DB::table('risk_access_facts')->where('event_type', 'access')->count());
    }

    public function test_historical_quiet_bulk_matches_individual_claims_and_does_not_double_count(): void
    {
        foreach (range(1, 20) as $id) {
            DB::table('risk_probe_logs')->insert(['id' => $id, 'app_id' => 1, 'status' => 'ok', 'risk_score' => 0,
                'compliance_mode' => 0, 'ad_switch' => 1, 'created_at' => '2026-10-28 12:00:00']);
        }
        $ledger = app(RiskLedgerService::class);
        $ledger->applyLog(RiskProbeLog::find(1));
        $this->assertSame(20, $ledger->sync(50, 1));
        $this->assertSame(20, DB::table('risk_ledger_applied')->count());
        $this->assertSame(20, DB::table('risk_day_flags')->value('unclassified_logs'));
        $this->assertSame(0, $ledger->sync(50, 1));
        $this->assertSame(20, DB::table('risk_day_flags')->value('unclassified_logs'));
        $this->assertSame(0, DB::table('risk_access_facts')->count());
    }

    public function test_dossier_score_details_use_the_event_that_produced_the_highest_active_score(): void
    {
        $ip = '192.0.2.10';
        $time = '2026-11-27 12:00:00';
        $scope = ['app_id' => 1, 'ip' => $ip, 'device_identity' => 'd1', 'collected_at' => $time, 'reason_codes' => '["root","emulator","vpn"]'];
        $scopeId = DB::table('risk_scopes')->insertGetId($scope + ['risk_score' => 100]);
        DB::table('risk_scopes')->insert($scope + ['risk_score' => 95]);
        DB::table('risk_scopes')->insert(array_replace($scope, ['risk_score' => 100, 'reason_codes' => '["vpn"]']));
        $items = [['code' => 'root', 'points' => 80], ['code' => 'emulator', 'points' => 50], ['code' => 'vpn', 'points' => 0]];
        $logId = DB::table('risk_probe_logs')->insertGetId(['risk_score' => 100, 'status' => 'ok', 'probe_json' => json_encode([
            '_risk_basis' => ['decision' => ['score' => 100, 'uncapped_score' => 130, 'score_items' => $items]],
        ])]);
        DB::table('risk_timeline')->insert(['ip' => $ip, 'scope_id' => $scopeId, 'risk_score' => 100, 'source_log_id' => $logId,
            'event_type' => 'risk_hit', 'occurred_at' => $time]);

        $details = app(RiskDossierService::class)->scoreDetails(IpNormalizer::idFor($ip));

        $this->assertSame(100, $details['score']);
        $this->assertSame(100, $details['raw_score']);
        $this->assertSame(130, $details['uncapped_score']);
        $this->assertSame(array_slice($items, 0, 2), $details['items']);
        $this->assertFalse($details['recalculated']);
    }

    public function test_incremental_sync_publishes_cursor_and_dossier_together_and_is_idempotent(): void
    {
        $this->collect();
        $ledger = app(RiskLedgerService::class);
        $this->assertFalse($ledger->caughtUp());
        $this->assertSame(1, $ledger->sync(50, 1));
        $this->assertTrue($ledger->caughtUp());
        $this->assertSame(0, $ledger->sync(50, 1));
        $this->assertSame(1, DB::table('risk_intakes')->count());
        $this->assertNotNull($ledger->progress()['last_success_at']);
    }
}
