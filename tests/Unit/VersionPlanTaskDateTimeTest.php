<?php

namespace Tests\Unit;

use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Services\App\VersionPlanService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VersionPlanTaskDateTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        require_once database_path('migrations/2026_07_03_000002_create_app_version_plans_table.php');
        require_once database_path('migrations/2026_07_03_000003_create_app_version_plan_tasks_table.php');
        (new \CreateAppVersionPlansTable())->up();
        (new \CreateAppVersionPlanTasksTable())->up();
    }

    public function test_task_times_survive_storage_listing_and_copying_at_minute_precision(): void
    {
        $plan = AppVersionPlan::query()->create([
            'app_id' => 1, 'title' => '版本更新计划', 'version' => '1.2.0',
            'planned_release_at' => '2026-10-30',
        ]);
        $service = new VersionPlanService();
        $createTask = new \ReflectionMethod($service, 'createTask');
        foreach ([
            ['2026-10-29 09:07', '2026-10-30T18:42', '2026-10-29 09:07', '2026-10-30 18:42'],
            ['2026-10-28', '2026-10-29', '2026-10-28 00:00', '2026-10-29 00:00'],
            ['', null, '', ''],
        ] as [$submitted, $listed, $expectedSubmitted, $expectedListed]) {
            $createTask->invoke($service, $plan, [
                'market_channel' => 'ios', 'version' => '1.2.0',
                'submitted_at' => $submitted, 'listed_at' => $listed,
            ]);
            $task = AppVersionPlanTask::query()->latest('id')->first();
            $this->assertSame($expectedSubmitted === '' ? null : $expectedSubmitted . ':00', $task->submitted_at);
            $this->assertSame($expectedListed === '' ? null : $expectedListed . ':00', $task->listed_at);
        }

        $result = $service->listByApp(1)[0];
        $this->assertSame('2026-10-30', $result['planned_release_at']);
        $this->assertSame('2026-10-29 09:07', $result['tasks'][0]['submitted_at']);
        $this->assertSame('2026-10-30 18:42', $result['tasks'][0]['listed_at']);
        $this->assertSame('2026-10-28 00:00', $result['tasks'][1]['submitted_at']);
        $this->assertSame('', $result['tasks'][2]['listed_at']);
        $this->assertSame($result['tasks'][0]['listed_at'], $service->copyPlan(1, $plan->id)['tasks'][0]['listed_at']);
    }
}
