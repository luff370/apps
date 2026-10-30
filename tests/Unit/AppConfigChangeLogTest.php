<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\App\AppConfigController;
use App\Models\AppConfig;
use App\Models\AppConfigChangeLog;
use App\Services\App\AppConfigService;
use App\Services\App\VersionPlanService;
use App\Support\Services\AppConfigService as ConfigCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppConfigChangeLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2024_11_07_132609_create_app_config_table.php'))->up();
        (require database_path('migrations/2026_10_30_120000_create_app_config_change_logs_table.php'))->up();
        require_once database_path('migrations/2026_07_03_000002_create_app_version_plans_table.php');
        require_once database_path('migrations/2026_07_03_000003_create_app_version_plan_tasks_table.php');
        (new \CreateAppVersionPlansTable())->up();
        (new \CreateAppVersionPlanTasksTable())->up();
        Schema::table('app_config', fn(Blueprint $table) => $table->unique(['app_id', 'channel', 'key']));
        Schema::create('system_apps', function (Blueprint $table) {
            $table->id();
            $table->integer('is_del')->default(0);
            $table->json('markets')->nullable();
        });
        Schema::create('risk_review_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id');
            $table->string('attempt_key', 64);
            $table->string('version_name', 64);
            $table->string('market_channel', 64);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('listed_at')->nullable();
            $table->string('status', 24);
            $table->dateTime('effective_at');
        });
        Request::macro('adminInfo', fn() => ['id' => 7, 'account' => 'test-admin']);
    }

    protected function tearDown(): void
    {
        Request::flushMacros();
        parent::tearDown();
    }

    private function service(): AppConfigService
    {
        return app(AppConfigService::class);
    }

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'app_id' => 1, 'channel' => 'huawei', 'version' => '1.0.0',
            'key' => AppConfig::AUTO_ADD_WHITE_LIST_KEY, 'name' => '自动添加白名单',
            'value' => '3', 'is_enable' => 0, 'remark' => AppConfig::AUTO_ADD_WHITE_LIST_REMARK,
        ], $overrides);
    }

    private function plan(int $appId, string $channel, string $status, string $version = '2.0.0', int $id = 0): array
    {
        return (new VersionPlanService())->savePlan($appId, [
            'id' => $id, 'title' => '测试上线', 'version' => $version, 'status' => '草稿',
            'tasks' => [['market_channel' => $channel, 'version' => $version, 'status' => $status]],
        ]);
    }

    public function test_review_sync_is_scoped_to_app_and_channel_and_preserves_value(): void
    {
        $target = AppConfig::query()->create($this->attributes());
        $otherChannel = AppConfig::query()->create($this->attributes(['channel' => 'oppo']));
        $otherApp = AppConfig::query()->create($this->attributes(['app_id' => 2]));
        ConfigCache::cacheByAppId(1);

        $plan = $this->plan(1, 'huawei', '审核中');
        $this->assertSame(['version' => '2.0.0', 'is_enable' => 1, 'value' => '3'], $target->fresh()->only(['version', 'is_enable', 'value']));
        $this->assertSame(0, $otherChannel->fresh()->is_enable);
        $this->assertSame('1.0.0', $otherApp->fresh()->version);
        $this->assertSame(['auto_add_white_list' => '3'], ConfigCache::getConfigsByAppIdChannelVersion(1, 'huawei', '2.0.0'));
        $this->assertSame([], ConfigCache::getConfigsByAppIdChannelVersion(1, 'huawei', '1.0.0'));
        $this->assertSame([], ConfigCache::getConfigsByAppIdChannelVersion(1, 'oppo', '2.0.0'));
        $log = AppConfigChangeLog::query()->latest('id')->first();
        $this->assertSame('version_plan', $log->source);
        $this->assertSame(0, $log->before['is_enable']);
        $this->assertSame(1, $log->after['is_enable']);

        $count = AppConfigChangeLog::query()->count();
        $this->plan(1, 'huawei', '审核中', '2.0.0', $plan['id']);
        $this->assertSame($count, AppConfigChangeLog::query()->count(), '重复保存不产生虚假变更');
        $this->plan(1, 'huawei', '已上架', '2.0.0', $plan['id']);
        $this->assertSame(0, $target->fresh()->is_enable);
        $this->assertSame('3', $target->fresh()->value);
        $this->assertSame([], ConfigCache::getConfigsByAppIdChannelVersion(1, 'huawei', '2.0.0'));
    }

    public function test_only_review_creates_config_and_every_other_status_disables_it(): void
    {
        foreach (['待提交', '已提交', '已过审', '已拒绝', '待上架', '已上架', '已下架', '暂停'] as $status) {
            $this->plan(1, 'huawei', $status);
        }
        $this->assertSame(0, AppConfig::query()->count());
        $this->plan(1, 'huawei', '审核中');
        $config = AppConfig::query()->first();
        $this->assertSame('1', $config->value);
        foreach (['待提交', '已提交', '已过审', '已拒绝', '待上架', '已上架', '已下架', '暂停'] as $status) {
            $this->plan(1, 'huawei', '审核中');
            $this->plan(1, 'huawei', $status);
            $this->assertSame(0, $config->fresh()->is_enable, $status);
        }
    }

    public function test_manual_crud_and_scope_moves_are_logged_for_both_apps(): void
    {
        $service = $this->service();
        $config = $service->save($this->attributes());
        $service->update($config->id, ['value' => '2', 'is_enable' => 1]);
        $service->update($config->id, ['app_id' => 2, 'channel' => 'oppo']);
        $service->delete($config->id);
        $this->assertSame(0, AppConfig::query()->count());
        $logs = AppConfigChangeLog::query()->orderBy('id')->get();
        $this->assertSame(['create', 'update', 'update', 'delete'], $logs->pluck('action')->all());
        $this->assertSame('3', $logs[1]->before['value']);
        $this->assertSame('2', $logs[1]->after['value']);
        $this->assertSame('test-admin', $logs[1]->admin_account);
        $this->assertSame('admin', $logs[1]->source);
        $this->assertNull($logs[3]->after);
        $this->assertSame(3, $service->changeLogs(1)['count']);
        $this->assertSame(2, $service->changeLogs(2)['count']);
        $this->assertSame(0, $service->changeLogs(3)['count']);
        $page = $service->changeLogs(1, 2, 1);
        $this->assertCount(1, $page['list']);
        $this->assertSame($logs[1]->id, $page['list'][0]['id']);
    }

    public function test_all_parameters_and_filter_switch_are_logged_without_noop_records(): void
    {
        $service = $this->service();
        $config = $service->save($this->attributes(['key' => 'other_parameter', 'value' => 'hello']));
        $service->update($config->id, ['value' => 'hello']);
        $this->assertSame(1, AppConfigChangeLog::query()->count());
        $service->setUserWhiteListFilterEnabled(1, 1);
        $logs = $service->changeLogs(1);
        $this->assertSame(3, $logs['count']);
        $this->assertSame('user_white_list_filter', $logs['list'][0]['key']);
    }

    public function test_failed_plan_save_rolls_back_config_and_audit_logs(): void
    {
        DB::beginTransaction();
        $this->plan(1, 'huawei', '审核中');
        $this->assertSame(1, AppConfigChangeLog::query()->count());
        DB::rollBack();
        $this->assertSame(0, AppConfig::query()->count());
        $this->assertSame(0, AppConfigChangeLog::query()->count());
        $this->assertNull(cache()->get(sprintf(AppConfig::CACHE_BY_APPID, 1)));
    }

    public function test_backend_editing_app_and_channel_refreshes_both_caches_and_logs_endpoint(): void
    {
        $config = $this->service()->save($this->attributes(['is_enable' => 1]));
        ConfigCache::cacheByAppId(1);
        ConfigCache::cacheByAppId(2);
        $this->app->instance('request', Request::create('/admin/app/app_config/' . $config->id, 'PUT',
            $this->attributes(['app_id' => 2, 'channel' => 'oppo', 'is_enable' => 1])
        ));
        $controller = app(AppConfigController::class);
        $this->assertSame(200, $controller->update($config->id)->getData(true)['status']);
        $this->assertSame([], ConfigCache::getConfigsByAppIdChannelVersion(1, 'huawei', '1.0.0'));
        $this->assertSame(['auto_add_white_list' => '3'], ConfigCache::getConfigsByAppIdChannelVersion(2, 'oppo', '1.0.0'));
        $this->assertSame([], ConfigCache::getConfigsByAppIdChannelVersion(2, 'huawei', '1.0.0'));
        $this->app->instance('request', Request::create('/admin/app/app_config/change_logs', 'GET', ['app_id' => 2]));
        $data = $controller->changeLogs()->getData(true);
        $this->assertSame(200, $data['status']);
        $this->assertSame(1, $data['data']['count']);
        $this->assertSame('huawei', $data['data']['list'][0]['before']['channel']);
        $this->assertSame('oppo', $data['data']['list'][0]['after']['channel']);
    }

    public function test_log_search_filters_current_app_and_uses_model_channel_names(): void
    {
        $service = $this->service();
        $config = $service->save($this->attributes());
        $service->update($config->id, ['channel' => 'oppo', 'name' => '白名单更新']);
        $service->save($this->attributes(['app_id' => 2]));
        $service->save($this->attributes(['channel' => 'all', 'key' => 'global_switch', 'name' => '全局开关']));
        $service->save($this->attributes(['channel' => 'yyb', 'key' => 'alias_switch']));
        $service->save($this->attributes(['channel' => 'custom', 'key' => 'custom_switch']));

        $logs = $service->changeLogs(1, 1, 20, ['keyword' => 'auto_add_white_list', 'channel' => 'huawei']);
        $this->assertSame(2, $logs['count'], '渠道变更前后的记录均可搜索，其他应用不混入');
        $this->assertSame('OPPO', $logs['list'][0]['channel_name']);
        $this->assertSame('华为', $logs['list'][0]['before_channel_name']);
        $this->assertSame('OPPO', $logs['list'][0]['after_channel_name']);
        $this->assertSame(1, $service->changeLogs(1, 1, 20, ['keyword' => '白名单更新'])['count']);
        $this->assertSame(2, $service->changeLogs(1, 1, 20, ['keyword' => 'test-admin', 'channel' => 'huawei'])['count']);
        $global = $service->changeLogs(1, 1, 20, ['channel' => 'all']);
        $this->assertSame(1, $global['count']);
        $this->assertSame('全部渠道', $global['list'][0]['channel_name']);
        $this->assertSame('应用宝', $service->changeLogs(1, 1, 20, ['channel' => 'yingyongbao'])['list'][0]['channel_name']);
        $this->assertSame('custom', $service->changeLogs(1, 1, 20, ['channel' => 'custom'])['list'][0]['channel_name']);
        $this->assertSame(0, $service->changeLogs(1, 1, 20, ['keyword' => '不存在'])['count']);
        $this->assertContains(['value' => 'huawei', 'label' => '华为'], $logs['channels']);
    }
}
