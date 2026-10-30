<?php

namespace Tests\Unit;

use App\Models\RiskProbeLog;
use App\Services\Risk\ActivityEventService;
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
        $decision = ['score' => 80, 'reasons' => ['vpn'], 'compliance_mode' => 1, 'ad_switch' => 0];
        $log = RiskProbeLog::query()->create(['app_id' => 1, 'status' => 'ok', 'risk_score' => 80, 'risk_reasons' => ['vpn'],
            'compliance_mode' => 1, 'ad_switch' => 0, 'user_uuid' => 'device-a', 'device_sn' => 'install-a',
            'app_version' => '1.0', 'market_channel' => 'huawei', 'client_ip' => '192.0.2.1', 'route' => 'api/report',
            'created_at' => '2026-10-30 10:00:00', 'probe_json' => ['_risk_basis' => ['hash' => RiskPolicyService::basis($decision)], '_identity_kind' => 'device']]);
        $log->created_at = '2026-10-30 10:00:00';
        $log->save();
        app(RiskLedgerService::class)->applyLog($log);

        return DB::table('risk_scopes')->first();
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
        foreach (range(0, 61) as $i) {
            $at = Carbon::now()->subSeconds(1830 - $i * 30)->toDateTimeString();
            DB::table('risk_activity_events')->insert(['event_id' => 'live-'.$i, 'session_id' => 'live', 'app_id' => 1, 'device_identity' => 'device-a',
                'event_seq' => $i + 1, 'monotonic_elapsed_ms' => $i * 30000, 'online_verified' => 1, 'projected_at' => now(),
                'foreground' => 1, 'screen_interactive' => 1, 'interaction' => 1, 'occurred_at' => $at, 'received_at' => $at]);
        }
        $release = app(RiskReleaseEngine::class)->tryRelease($scope);
        $this->assertNotNull($release);
        $base = ['score' => 80, 'reasons' => ['vpn'], 'compliance_mode' => 1, 'ad_switch' => 0];
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
        $this->assertSame(1, $policy->overlay(['score' => 0, 'reasons' => [], 'compliance_mode' => 0, 'ad_switch' => 1], 1, 'device-a', [])['compliance_mode']);
        $this->assertSame('restored', app(RiskReleaseEngine::class)->revoke($release['id'], 'same-request', 1, 1, 'admin')['result']);
        $this->assertNull(app(RiskReleaseEngine::class)->tryRelease(DB::table('risk_scopes')->first()));
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
            'score' => 80, 'reasons' => ['vpn'], 'decision' => ['score' => 80, 'reasons' => ['vpn'], 'compliance_mode' => 1, 'ad_switch' => 0],
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
        $this->assertSame(1, DB::table('risk_releases')->count());
        $this->assertSame('released', DB::table('risk_scopes')->value('state'));
        $this->assertSame(0, app(\App\Services\Risk\RiskActivityProjector::class)->sync());
        $this->assertSame(1, DB::table('risk_releases')->count());
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
