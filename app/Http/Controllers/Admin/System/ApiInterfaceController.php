<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\SystemApiInterfaceService;

class ApiInterfaceController extends Controller
{
    public function __construct(SystemApiInterfaceService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $where = $this->getMore([
            ['keyword', ''],
            ['module', ''],
            ['method', ''],
            ['is_enable', ''],
        ]);

        return $this->success($this->service->getAllByPage($where));
    }

    public function detail($id)
    {
        $detail = $this->service->getDetail((int) $id);
        if (empty($detail)) {
            return $this->fail('接口不存在');
        }

        return $this->success($detail);
    }

    public function save()
    {
        $data = $this->getMore([
            ['id', 0],
            ['name', ''],
            ['module', ''],
            ['path', ''],
            ['method', 'POST'],
            ['request_params', []],
            ['response_params', []],
            ['is_enable', 1],
            ['remark', ''],
        ]);

        if (empty($data['path'])) {
            return $this->fail('接口路径不能为空');
        }

        $this->service->saveOrUpdate($data);
        return $this->success('保存成功');
    }

    public function delete($id)
    {
        $this->service->delete((int) $id);
        return $this->success('删除成功');
    }

    public function routeSql()
    {
        return $this->success(['sql' => $this->service->buildInsertSqlFromApiRoutes()]);
    }

    public function importRoutes()
    {
        return $this->success($this->service->importFromApiRoutes(), '导入完成');
    }
}
