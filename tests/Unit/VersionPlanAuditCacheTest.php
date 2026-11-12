<?php

namespace Tests\Unit;

use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Services\App\VersionPlanService;
use App\Services\System\AppVersionServices;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VersionPlanAuditCacheTest extends TestCase
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
        (require database_path('migrations/2024_11_07_132609_create_app_config_table.php'))->up();
        (require database_path('migrations/2026_10_30_120000_create_app_config_change_logs_table.php'))->up();
        Schema::create('system_apps', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('is_del')->default(0);
            $table->string('name')->default('');
            $table->text('markets')->nullable();
        });
        Schema::create('risk_review_history', function (Blueprint $table) {
            $table->id();
            $table->integer('app_id');
            foreach (['attempt_key', 'version_name', 'market_channel', 'status', 'effective_at'] as $field) {
                $table->string($field);
            }
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('listed_at')->nullable();
        });
    }

    public function test_todo_plans_keep_only_my_developing_and_pending_plans(): void
    {
        DB::table('system_apps')->insert([
            ['id' => 1, 'is_del' => 0, 'name' => '应用甲'],
            ['id' => 2, 'is_del' => 0, 'name' => '应用乙'],
            ['id' => 3, 'is_del' => 1, 'name' => '已删除应用'],
        ]);
        $mine = AppVersionPlan::query()->create([
            'app_id' => 1, 'title' => '我的计划', 'version' => '1.2.0', 'status' => '开发中',
            'owner_name' => '张三（zhang）', 'remark' => '修支付',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '别人的计划', 'version' => '2.0.0', 'status' => '待上架',
            'owner_name' => '李四', 'remark' => '别人的',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 1, 'title' => '已上架', 'version' => '1.0.0', 'status' => '已上架',
            'owner_name' => '张三（zhang）', 'remark' => '不上首页',
        ]);
        $taskOwned = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '渠道负责人', 'version' => '3.0.0', 'status' => '待上架',
            'owner_name' => '李四', 'remark' => '我负责渠道',
        ]);
        AppVersionPlanTask::query()->create([
            'plan_id' => $taskOwned->id, 'market_channel' => 'ios', 'version' => '3.0.0',
            'owner_name' => '张三', 'status' => '待提交',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 3, 'title' => '已删除应用', 'version' => '9.0.0', 'status' => '开发中',
            'owner_name' => '张三', 'remark' => '应用已删',
        ]);

        $list = app(VersionPlanService::class)->todoPlansForAdmin([
            'real_name' => '张三',
            'account' => 'zhang',
        ]);

        $this->assertSame([$taskOwned->id, $mine->id], array_column($list, 'id'));
        $this->assertSame('应用乙', $list[0]['app_name']);
        $this->assertSame('待上架', $list[0]['status']);
        $this->assertSame('3.0.0', $list[0]['version']);
        $this->assertSame('我负责渠道', $list[0]['remark']);
        $this->assertSame('应用甲', $list[1]['app_name']);
        $this->assertSame('开发中', $list[1]['status']);
    }

    public function test_status_changes_invalidate_a_warmed_cache_in_both_directions(): void
    {
        $form = $this->reviewingPlan();
        $this->assertSame(1, $this->audit());
        $form['tasks'][0]['status'] = '已上架';
        app(VersionPlanService::class)->savePlan(1, $form);
        $this->assertSame(0, $this->audit());
        $form['tasks'][0]['status'] = '审核中';
        app(VersionPlanService::class)->savePlan(1, $form);
        $this->assertSame(1, $this->audit());
    }

    public function test_changing_version_invalidates_old_and_new_combinations(): void
    {
        $form = $this->reviewingPlan();
        $this->assertSame(1, $this->audit());
        $this->assertSame(0, $this->audit('ios', '2.0'));
        $form['tasks'][0]['version'] = '2.0';
        app(VersionPlanService::class)->savePlan(1, $form);
        $this->assertSame(0, $this->audit());
        $this->assertSame(1, $this->audit('ios', '2.0'));
    }

    public function test_changing_channel_invalidates_old_and_new_combinations(): void
    {
        $form = $this->reviewingPlan();
        $this->assertSame(1, $this->audit());
        $this->assertSame(0, $this->audit('huawei'));
        $form['tasks'][0]['market_channel'] = 'huawei';
        app(VersionPlanService::class)->savePlan(1, $form);
        $this->assertSame(0, $this->audit());
        $this->assertSame(1, $this->audit('huawei'));
    }

    public function test_removing_a_channel_invalidates_its_cache(): void
    {
        $form = $this->reviewingPlan();
        $this->assertSame(1, $this->audit());
        $form['tasks'] = [['market_channel' => 'huawei', 'version' => '1.0', 'status' => '已提交']];
        app(VersionPlanService::class)->savePlan(1, $form);
        $this->assertSame(0, $this->audit());
    }

    public function test_delete_invalidates_cache_and_falls_back_to_other_matching_plan(): void
    {
        $form = $this->reviewingPlan();
        $other = AppVersionPlan::query()->create(['app_id' => 1, 'title' => 'Older', 'version' => '1.0']);
        $olderTask = AppVersionPlanTask::query()->create([
            'plan_id' => $other->id, 'market_channel' => 'ios', 'version' => '1.0',
            'status' => '已上架',
        ]);
        DB::table('app_version_plan_tasks')->where('id', $olderTask->id)->update(['updated_at' => '2020-01-01 00:00:00']);
        $this->assertSame(1, $this->audit());
        app(VersionPlanService::class)->deletePlan(1, $form['id']);
        $this->assertSame(0, $this->audit());
        app(VersionPlanService::class)->deletePlan(1, $other->id);
        $this->assertSame(0, $this->audit());
    }

    public function test_failed_save_rolls_back_tasks_without_invalidating_cache(): void
    {
        $form = $this->reviewingPlan();
        $this->assertSame(1, $this->audit());
        $form['tasks'] = [['market_channel' => 'ios', 'version' => '']];
        try {
            app(VersionPlanService::class)->savePlan(1, $form);
            $this->fail('Expected validation failure');
        } catch (\App\Exceptions\AdminException $e) {
            $this->assertSame(1, AppVersionPlanTask::query()->where('plan_id', $form['id'])->count());
            $this->assertSame(1, cache(AppVersionServices::auditStatusCacheKey(1, 'ios', '1.0')));
            $this->assertSame(1, $this->audit());
        }
    }

    private function reviewingPlan(): array
    {
        return app(VersionPlanService::class)->savePlan(1, [
            'title' => '审核测试', 'version' => '1.0',
            'tasks' => [['market_channel' => 'ios', 'version' => '1.0', 'status' => '审核中']],
        ]);
    }

    private function audit(string $channel = 'ios', string $version = '1.0'): int
    {
        return AppVersionServices::getAuditStatusByVersion(1, $channel, $version);
    }
}
