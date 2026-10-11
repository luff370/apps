<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Admin\Controller;
use App\Services\Cms\AgentService;

/** 内容管理下的智能体配置。每个应用可以单独调整提示词、知识和温度。 */
class AgentController extends Controller
{
    public function __construct(AgentService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['keyword', ''],
            ['status', ''],
        ]);

        return $this->success($this->service->getList($where));
    }

    public function show($id)
    {
        return $this->success($this->service->find((int) $id));
    }

    public function store()
    {
        $this->service->saveAgent($this->formData());

        return $this->success(100021);
    }

    public function update($id)
    {
        $data = $this->formData();
        $data['id'] = $id;
        $this->service->saveAgent($data);

        return $this->success(100001);
    }

    public function destroy($id)
    {
        $this->service->remove((int) $id);

        return $this->success(100002);
    }

    public function setStatus($id, $value)
    {
        $this->service->setStatus((int) $id, (int) $value);

        return $this->success(100014);
    }

    private function formData(): array
    {
        return $this->getMore([
            ['app_id', 0],
            ['name', ''],
            ['agent_key', ''],
            ['system_prompt', ''],
            ['knowledge', ''],
            ['temperature', ''],
            ['status', 1],
            ['remark', ''],
            ['inputs', []],
        ]);
    }
}
