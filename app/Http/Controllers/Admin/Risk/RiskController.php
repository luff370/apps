<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Http\Controllers\Admin\Controller;
use App\Services\Risk\RiskAdminService;
use App\Services\Risk\RiskListService;
use App\Services\Risk\RiskStrategyService;

class RiskController extends Controller
{
    public function __construct(
        private RiskAdminService $adminService,
        private RiskStrategyService $strategyService,
        private RiskListService $listService,
    ) {
    }

    public function overview()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['time', ''],
        ]);

        return $this->success($this->adminService->overview($filter));
    }

    public function devices()
    {
        $filter = $this->getMore([
            ['market_channel', ''],
            ['version', ''],
            ['risk_level', ''],
            ['decision', ''],
            ['keyword', ''],
            ['time', ''],
            ['app_id', ''],
        ]);

        return $this->success($this->adminService->deviceList($filter));
    }

    public function deviceDetail($id)
    {
        $data = $this->adminService->deviceDetail((int) $id);
        if (!$data) {
            return $this->fail('设备记录不存在');
        }

        return $this->success($data);
    }

    public function deviceGraph($id)
    {
        return $this->success($this->adminService->deviceGraph($id));
    }

    public function events()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['event_type', ''],
            ['decision', ''],
            ['risk_level', ''],
            ['keyword', ''],
            ['time', ''],
        ]);

        return $this->success($this->adminService->eventList($filter));
    }

    public function clusters()
    {
        $filter = $this->getMore([
            ['cluster_type', ''],
            ['keyword', ''],
            ['app_id', ''],
        ]);

        return $this->success($this->adminService->clusters($filter));
    }

    public function strategies()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['keyword', ''],
        ]);

        return $this->success($this->strategyService->list($filter));
    }

    public function strategyCreate()
    {
        return $this->success($this->strategyService->createForm());
    }

    public function strategyEdit($id)
    {
        return $this->success($this->strategyService->editForm((int) $id));
    }

    public function strategyStore()
    {
        return $this->success('当前评分策略由服务端阈值驱动，已保留现有配置');
    }

    public function strategyUpdate($id)
    {
        return $this->success('当前评分策略由服务端阈值驱动，已保留现有配置');
    }

    public function strategySetField($id)
    {
        return $this->success(100014);
    }

    public function strategyDestroy($id)
    {
        return $this->fail('默认评分策略不可删除');
    }

    public function lists()
    {
        $filter = $this->getMore([
            ['list_type', ''],
            ['target_type', ''],
            ['app_id', ''],
            ['keyword', ''],
        ]);

        return $this->success($this->listService->getAllByPage($filter, ['*'], ['id' => 'desc']));
    }

    public function listCreate()
    {
        $listType = (string) request()->get('list_type', 'blacklist');

        return $this->success($this->listService->createForm($listType));
    }

    public function listStore()
    {
        $data = $this->listPayload();
        $this->listService->saveRow($data);

        return $this->success(100021);
    }

    public function listEdit($id)
    {
        return $this->success($this->listService->updateForm((int) $id));
    }

    public function listUpdate($id)
    {
        $this->listService->saveRow($this->listPayload(), (int) $id);

        return $this->success(100001);
    }

    public function listSetField($id)
    {
        $field = (string) request()->get('field', request()->input('field', ''));
        $value = request()->get('value', request()->input('value'));
        if ($field === '') {
            return $this->fail('缺少字段');
        }
        $this->listService->setField((int) $id, $field, $value);

        return $this->success(100014);
    }

    public function listDestroy($id)
    {
        $this->listService->remove((int) $id);

        return $this->success(100002);
    }

    private function listPayload(): array
    {
        return $this->getMore([
            ['list_type', 'blacklist'],
            ['target_type', 'device_identity'],
            ['target_value', ''],
            ['app_id', 0],
            ['decision', 'block'],
            ['status', 1],
            ['remark', ''],
            ['operator', ''],
        ]);
    }
}
