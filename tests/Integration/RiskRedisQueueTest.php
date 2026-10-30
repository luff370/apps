<?php

namespace Tests\Integration;

use App\Jobs\Risk\ProcessRiskActivity;
use App\Jobs\Risk\ProcessRiskProbe;
use App\Services\Risk\RiskActivityProjector;
use App\Services\Risk\RiskLedgerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Opt-in, local Redis only; never clears shared queues or databases. */
class RiskRedisQueueTest extends TestCase
{
    private ?string $queueName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RISK_REDIS_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set RISK_REDIS_INTEGRATION=1 to exercise local Redis.');
        }
        config(['database.redis.risk' => [
            'host' => '127.0.0.1', 'port' => 6379, 'database' => 0,
            'timeout' => 0.2, 'read_timeout' => 0.2, 'read_write_timeout' => 0.2,
        ], 'queue.failed.driver' => 'null']);
        $this->queueName = 'risk-test-'.bin2hex(random_bytes(12));
    }

    protected function tearDown(): void
    {
        if ($this->queueName !== null) {
            Queue::connection('risk')->clear($this->queueName);
        }
        parent::tearDown();
    }

    public function test_redis_serializes_probe_job_and_worker_executes_it_once(): void
    {
        $this->assertSame('redis', config('queue.connections.risk.driver'));
        $ledger = \Mockery::mock(RiskLedgerService::class);
        $ledger->shouldReceive('sync')->once()->with(200, 2)->andReturn(0);
        $ledger->shouldReceive('cursor')->once()->andReturn(0);
        $this->app->instance(RiskLedgerService::class, $ledger);
        Queue::connection('risk')->push(new ProcessRiskProbe(99), '', $this->queueName);
        $this->assertSame(1, Queue::connection('risk')->size($this->queueName));
        $this->workOnce();
        $this->assertSame(0, Queue::connection('risk')->size($this->queueName));
    }

    public function test_redis_activity_job_runs_in_worker(): void
    {
        $projector = \Mockery::mock(RiskActivityProjector::class);
        $projector->shouldReceive('sync')->once()->with(100, 3)->andReturn(0);
        $this->app->instance(RiskActivityProjector::class, $projector);
        Queue::connection('risk')->push(new ProcessRiskActivity, '', $this->queueName);
        $this->assertSame(1, Queue::connection('risk')->size($this->queueName));
        $this->workOnce();
        $this->assertSame(0, Queue::connection('risk')->size($this->queueName));
    }

    private function workOnce(): void
    {
        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => 'risk', '--queue' => $this->queueName,
            '--once' => true, '--sleep' => 0, '--tries' => 1,
        ]));
    }
}
