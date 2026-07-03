<?php

namespace App\Http\Controllers\Admin\App;

use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Services\App\VersionPlanService;

class VersionPlanController extends Controller
{
    public function __construct(VersionPlanService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取应用版本规划列表，供版本规划页面替换本地 mock 数据。
     */
    public function index($appId)
    {
        return $this->success($this->service->listByApp((int)$appId));
    }

    /**
     * 新建或编辑版本规划，渠道任务随计划一起保存。
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
     * @throws AdminException
     */
    public function delete($appId, $id)
    {
        $this->service->deletePlan((int)$appId, (int)$id);

        return $this->success('删除成功');
    }
}
