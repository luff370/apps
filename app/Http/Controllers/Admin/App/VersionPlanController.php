<?php

namespace App\Http\Controllers\Admin\App;

use App\Exceptions\AdminException;
use App\Exceptions\AuthException;
use App\Http\Controllers\Admin\Controller;
use App\Models\SystemMenu;
use App\Services\App\VersionPlanService;
use App\Services\System\Admin\SystemRoleServices;

class VersionPlanController extends Controller
{
    public function __construct(VersionPlanService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取应用版本规划列表。
     *
     * 版本规划页最初靠本地 mock 数据演示，这里接入后端后，页面就能直接加载真实计划、
     * 强更记录和在架渠道状态。
     */
    public function index($appId)
    {
        return $this->success($this->service->listByApp((int)$appId));
    }

    public function tasks(SystemRoleServices $roles)
    {
        $admin = adminInfo();
        if ($admin['level'] && !SystemMenu::query()
            ->where('unique_auth', 'admin-app-version-update-tasks')
            ->where('is_del', 0)
            ->whereIn('id', $roles->getRoleIds($admin['roles']))
            ->exists()) {
            throw new AuthException(110000);
        }

        return $this->success($this->service->listTasks(request()->only([
            'owner', 'app_id', 'status', 'priority', 'channel', 'keyword', 'page', 'limit',
        ]), $admin));
    }

    /**
     * 当前管理员在开发中、待上架计划里的待办，供首页滚动提醒使用。
     */
    public function todos()
    {
        return $this->success(['list' => $this->service->todoPlansForAdmin(adminInfo() ?: [])]);
    }

    /**
     * 新建或编辑版本规划，渠道任务随计划一起保存。
     *
     * 不拆成两个接口，是为了让前端一次点击保存就能把计划头和任务集合一起落库，避免主表
     * 已保存而任务表还没更新的半成品状态。
     *
     * @throws AdminException
     */
    public function save($appId)
    {
        $plan = $this->service->savePlan((int)$appId, request()->all());

        return $this->success($plan, '保存成功');
    }

    /**
     * 复制一个已有版本规划及其渠道任务。
     *
     * 复制后的记录回到开发中，方便继续编辑，而不会误以为它已经上架。
     *
     * @throws AdminException
     */
    public function copy($appId, $id)
    {
        $plan = $this->service->copyPlan((int)$appId, (int)$id);

        return $this->success($plan, '复制成功');
    }

    /**
     * 删除版本规划，同时清理对应渠道任务。
     *
     * 级联删除放在服务层事务里执行，确保主表和任务表不会出现孤儿数据。
     *
     * @throws AdminException
     */
    public function delete($appId, $id)
    {
        $this->service->deletePlan((int)$appId, (int)$id);

        return $this->success('删除成功');
    }
}
