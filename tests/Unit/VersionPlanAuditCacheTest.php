<?php

namespace Tests\Unit;

use App\Dao\App\AppsDao;
use App\Dao\System\Attachment\SystemAttachmentDao;
use App\Exceptions\AdminException;
use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Models\SystemAttachment;
use App\Services\App\VersionPlanService;
use App\Services\System\AppVersionServices;
use App\Services\System\Attachment\SystemAttachmentServices;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        (require database_path('migrations/2026_11_23_000001_split_version_plan_owners.php'))->up();
        (require database_path('migrations/2024_11_07_132609_create_app_config_table.php'))->up();
        (require database_path('migrations/2026_10_30_120000_create_app_config_change_logs_table.php'))->up();
        Schema::create('system_apps', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('is_del')->default(0);
            $table->string('name')->default('');
            $table->string('platform')->default('ios');
            $table->string('package_name')->default('');
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

    public function test_pinned_admin_app_sorts_before_pagination_and_respects_filters(): void
    {
        DB::table('system_apps')->insert([
            ['id' => 10077, 'platform' => 'web'],
            ['id' => 10100, 'platform' => 'android'],
            ['id' => 10101, 'platform' => 'android'],
        ]);
        $dao = app(AppsDao::class);
        $options = ['pin_admin_app' => true];
        $this->assertSame([10077, 10101], $dao->getAll($options, ['id'], ['id' => 'desc'], [], 2, 1)->pluck('id')->all());
        $this->assertSame([10100], $dao->getAll($options, ['id'], ['id' => 'desc'], [], 2, 2)->pluck('id')->all());
        $this->assertSame([10101, 10100], $dao->getAll($options + ['platform' => 'android'], ['id'], ['id' => 'desc'])->pluck('id')->all());
    }

    public function test_web_plan_can_be_created_updated_and_copied_without_channel_tasks(): void
    {
        DB::table('system_apps')->insert(['id' => 10077, 'platform' => 'web']);
        $service = app(VersionPlanService::class);
        $input = ['title' => 'Web 更新', 'version' => '1.0'];
        $plan = $service->savePlan(10077, $input);
        $this->assertSame([], $plan['tasks']);

        $updated = $service->savePlan(10077, ['id' => $plan['id'], 'status' => '已上架', 'tasks' => []] + $input);
        $this->assertSame('已上架', $updated['status']);
        $this->assertSame([], $updated['tasks']);
        $this->assertSame([], $service->copyPlan(10077, $plan['id'])['tasks']);
        $this->assertSame(0, AppVersionPlanTask::query()->count());
    }

    public function test_mini_program_plan_accepts_wechat_and_alipay_and_rejects_native_channels(): void
    {
        DB::table('system_apps')->insert(['id' => 10, 'platform' => 'mini_program']);
        $service = app(VersionPlanService::class);
        $input = ['title' => '小程序发布', 'version' => '2.0'];
        $plan = $service->savePlan(10, $input + ['tasks' => [
            ['market_channel' => 'wechat', 'version' => '2.0'],
            ['market_channel' => 'alipay', 'version' => '2.0'],
        ]]);
        $this->assertSame(['wechat', 'alipay'], array_column($plan['tasks'], 'market_channel'));
        $copy = $service->copyPlan(10, $plan['id']);
        $this->assertSame(['wechat', 'alipay'], array_column($copy['tasks'], 'market_channel'));

        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('小程序渠道请选择微信或支付宝');
        $service->savePlan(10, $input + ['tasks' => [['market_channel' => 'ios', 'version' => '2.0']]]);
    }

    public function test_discard_removes_only_unpublished_images_in_the_current_remark_draft(): void
    {
        DB::table('system_apps')->insert(['id' => 10077, 'platform' => 'web']);
        Schema::create('system_attachment_category', function (Blueprint $table) {
            $table->id();
            $table->integer('pid');
            $table->string('name');
            $table->string('enname');
        });
        Schema::create('system_attachment', function (Blueprint $table) {
            $table->id('att_id');
            foreach (['name', 'real_name', 'att_dir', 'satt_dir', 'att_type'] as $column) {
                $table->string($column);
            }
            foreach (['pid', 'module_type', 'image_type', 'att_size', 'time'] as $column) {
                $table->integer($column);
            }
        });
        (require database_path('migrations/2026_11_24_000002_add_plan_remark_draft_to_attachments.php'))->up();
        $storage = Storage::fake('public');
        $media = app(SystemAttachmentServices::class);
        $plans = app(VersionPlanService::class);
        $draft = SystemAttachment::planRemarkDraftKey(str_repeat('a', 32), 1);
        $otherDraft = SystemAttachment::planRemarkDraftKey(str_repeat('a', 32), 2);
        $upload = fn($key) => $media->upload(0, UploadedFile::fake()
            ->create('plan-remark-' . str_repeat('b', 32) . '.png', 1, 'image/png'), '', 0, 'plan_remark', $key);
        $saved = $upload($draft);
        $unused = $upload($draft);
        $unrelated = $upload($otherDraft);

        $this->assertSame(0, app(SystemAttachmentDao::class)->count(['plan_remark_draft' => null]));
        $form = ['title' => 'Web 更新', 'version' => '1.0', 'remark_is_html' => true];
        $plans->savePlan(10077, $form + ['remark' => '<img src="' . $saved . '">'], $draft);
        $media->discardPlanRemarkDraft($draft);
        $media->discardPlanRemarkDraft($draft);

        $this->assertSame(1, app(SystemAttachmentDao::class)->count(['plan_remark_draft' => null]));
        $this->assertSame(1, SystemAttachment::query()->where('plan_remark_draft', $otherDraft)->count());
        foreach ([$saved, $unrelated] as $url) {
            $storage->assertExists(substr(parse_url($url, PHP_URL_PATH), strlen('/storage/')));
        }
        $storage->assertMissing(substr(parse_url($unused, PHP_URL_PATH), strlen('/storage/')));

        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('有备注图片已被清理');
        $plans->savePlan(10077, $form + ['remark' => '<img src="' . $unused . '">'], $draft);
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
            'developer_name' => '张三（zhang）', 'remark' => '修支付',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '别人的计划', 'version' => '2.0.0', 'status' => '待上架',
            'developer_name' => '李四', 'remark' => '别人的',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 1, 'title' => '已上架', 'version' => '1.0.0', 'status' => '已上架',
            'developer_name' => '张三（zhang）', 'remark' => '不上首页',
        ]);
        $operatorOwned = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '运营负责人', 'version' => '3.0.0', 'status' => '待上架',
            'developer_name' => '李四', 'operator_name' => '张三', 'remark' => '我负责运营',
        ]);
        AppVersionPlanTask::query()->create([
            'plan_id' => $operatorOwned->id, 'market_channel' => 'ios', 'version' => '3.0.0',
            'status' => '待提交',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 3, 'title' => '已删除应用', 'version' => '9.0.0', 'status' => '开发中',
            'developer_name' => '张三', 'remark' => '应用已删',
        ]);

        $list = app(VersionPlanService::class)->todoPlansForAdmin([
            'real_name' => '张三',
            'account' => 'zhang',
        ]);

        $this->assertSame([$operatorOwned->id, $mine->id], array_column($list, 'id'));
        $this->assertSame('应用乙', $list[0]['app_name']);
        $this->assertSame('待上架', $list[0]['status']);
        $this->assertSame('3.0.0', $list[0]['version']);
        $this->assertSame('我负责运营', $list[0]['remark']);
        $this->assertSame('应用甲', $list[1]['app_name']);
        $this->assertSame('开发中', $list[1]['status']);
    }

    public function test_task_list_backfills_legacy_owners_and_filters_either_role(): void
    {
        $migration = require database_path('migrations/2026_11_23_000001_split_version_plan_owners.php');
        $migration->down();
        DB::table('system_apps')->insert([
            ['id' => 1, 'is_del' => 0, 'name' => '应用甲'],
            ['id' => 2, 'is_del' => 0, 'name' => '应用乙'],
            ['id' => 3, 'is_del' => 1, 'name' => '已删除应用'],
        ]);
        $legacy = DB::table('app_version_plans')->insertGetId([
            'app_id' => 1, 'title' => '旧计划', 'version' => '1.0', 'owner_name' => '张三（zhang）',
            'remark' => '旧备注 < 标签',
        ]);
        $migration->up();

        Schema::create('system_admin', function (Blueprint $table) {
            $table->id();
            $table->string('account');
            $table->string('real_name');
            $table->boolean('is_del')->default(false);
            $table->boolean('status')->default(true);
        });
        DB::table('system_admin')->insert([
            ['id' => 5, 'account' => 'zhang', 'real_name' => '张三'],
            ['id' => 6, 'account' => 'li', 'real_name' => '李四'],
        ]);
        $operation = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '运营计划', 'version' => '2.0', 'developer_name' => '李四（li）',
            'operator_name' => 'zhang', 'status' => '待上架', 'priority' => 'P1',
            'remark' => '<p>备注<img src="/uploads/screenshot.png"></p>', 'remark_is_html' => true,
        ]);
        $operation->tasks()->create(['market_channel' => 'ios', 'version' => '2.0.1', 'status' => '已上架']);
        $other = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '他人的计划', 'version' => '3.0', 'developer_name' => '李四',
        ]);
        AppVersionPlan::query()->create([
            'app_id' => 3, 'title' => '已删除应用的计划', 'version' => '4.0', 'operator_name' => '张三',
        ]);
        $listed = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '已上架计划', 'version' => '5.0', 'status' => '已上架',
            'operator_name' => '张三',
        ]);
        $legacyListed = AppVersionPlan::query()->create([
            'app_id' => 2, 'title' => '旧版已完成计划', 'version' => '6.0', 'status' => '已完成',
            'operator_name' => '张三',
        ]);

        $service = app(VersionPlanService::class);
        $admin = ['id' => 5, 'real_name' => '张三', 'account' => 'zhang'];
        $mine = $service->listTasks([], $admin);
        $this->assertSame(2, $mine['count']);
        $this->assertSame('5', $mine['current_admin_id']);
        $legacyPlan = collect($mine['list'])->firstWhere('id', $legacy);
        $this->assertSame('张三（zhang）', $legacyPlan['developer_name']);
        $this->assertSame('张三（zhang）', $legacyPlan['operator_name']);
        $this->assertSame('旧备注 < 标签', $legacyPlan['remark']);
        $this->assertFalse($legacyPlan['remark_is_html']);

        $filtered = $service->listTasks([
            'owner' => 'all', 'app_id' => 2, 'status' => '待上架', 'priority' => 'P1',
            'channel' => 'ios', 'keyword' => '2.0.1',
        ], $admin);
        $this->assertSame([$operation->id], array_column($filtered['list'], 'id'));
        $this->assertTrue($filtered['list'][0]['remark_is_html']);
        $this->assertSame($operation->remark, $filtered['list'][0]['remark']);
        $this->assertSame(3, $service->listTasks(['owner' => 'all', 'limit' => 1], $admin)['count']);
        $this->assertSame(5, $service->listTasks(['owner' => 'all', 'hide_listed' => 'false', 'limit' => 1], $admin)['count']);
        $onlyListed = $service->listTasks(['status' => '已上架', 'hide_listed' => '0'], $admin);
        $this->assertSame([$legacyListed->id, $listed->id], array_column($onlyListed['list'], 'id'));
        $this->assertSame(['已上架', '已上架'], array_column($onlyListed['list'], 'status'));
        $this->assertEqualsCanonicalizing(
            [$other->id, $operation->id],
            array_column($service->listTasks(['owner' => '6'], $admin)['list'], 'id')
        );
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
