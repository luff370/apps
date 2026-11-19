<?php

namespace Tests\Unit;

use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Services\App\AppsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppsServiceListedVersionTest extends TestCase
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
        Schema::create('app_config', function (Blueprint $table) {
            $table->integer('app_id');
            $table->string('channel');
            $table->string('key');
            $table->integer('is_enable');
        });
    }

    public function test_list_uses_latest_listed_task_per_app_and_market(): void
    {
        $latest = $this->task(1, 'huawei', '2.0.0', '2026-11-19 12:35:00');
        $this->task(1, 'huawei', '1.0.0', '2026-11-19 12:34:00');
        $this->task(1, 'huawei', '3.0.0', '2026-11-19 12:36:00', '审核中');
        $xiaomi = $this->task(1, 'xiaomi', '1.5.0', '2026-11-18 10:00:00');
        $otherApp = $this->task(2, 'huawei', '9.0.0', '2026-11-19 13:00:00');

        $rows = app(AppsService::class)->tidyListData([
            ['id' => 1, 'markets' => [
                ['market_channel' => 'huawei', 'status' => 1, 'version' => '0.9.0'],
                ['market_channel' => 'oppo', 'status' => 1, 'version' => '1.1.0'],
            ]],
            ['id' => 2, 'markets' => []],
        ]);
        $markets = array_column($rows[0]['markets'], null, 'market_channel');
        $this->assertSame('2.0.0', $markets['huawei']['version']);
        $this->assertSame($latest->id, $markets['huawei']['source_task_id']);
        $this->assertSame($latest->name, $markets['huawei']['name']);
        $this->assertSame($xiaomi->id, $markets['xiaomi']['source_task_id']);
        $this->assertSame('1.1.0', $markets['oppo']['version']);
        $this->assertSame($otherApp->id, $rows[1]['markets'][0]['source_task_id']);
    }

    public function test_equal_times_use_latest_id_and_missing_listed_time_uses_updated_time(): void
    {
        $this->task(1, 'ios', '1.0.0', '2026-11-19 09:00:00');
        $latest = $this->task(1, 'ios', '1.1.0', '2026-11-19 09:00:00');
        $fallback = $this->task(1, 'xiaomi', '2.0.0', null);
        $fallback->updated_at = '2026-11-19 10:00:00';
        $fallback->save();
        $this->task(1, 'xiaomi', '1.0.0', '2026-11-19 09:00:00');

        $rows = app(AppsService::class)->tidyListData([['id' => 1, 'markets' => []]]);
        $markets = array_column($rows[0]['markets'], null, 'market_channel');
        $this->assertSame($latest->id, $markets['ios']['source_task_id']);
        $this->assertSame($fallback->id, $markets['xiaomi']['source_task_id']);
    }

    private function task(int $appId, string $channel, string $version, ?string $listedAt, string $status = '已上架'): AppVersionPlanTask
    {
        $plan = AppVersionPlan::query()->create([
            'app_id' => $appId, 'title' => $version, 'version' => $version,
        ]);

        return AppVersionPlanTask::query()->create([
            'plan_id' => $plan->id, 'market_channel' => $channel,
            'name' => $channel . ' ' . $version, 'version' => $version,
            'listed_at' => $listedAt, 'status' => $status,
        ]);
    }
}
