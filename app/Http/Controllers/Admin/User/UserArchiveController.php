<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserArchiveService;

/**
 * 用户档案管理
 */
class UserArchiveController extends Controller
{
    public function __construct(UserArchiveService $service)
    {
        $this->service = $service;
    }

    /**
     * 档案列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['app_version', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc'], ['user']);

        return $this->success($data);
    }

    /**
     * 编辑表单
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm((int) $id));
    }

    /**
     * 保存档案
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['name', ''],
            ['gender', ''],
            ['calendar', ''],
            ['birth_date', ''],
            ['birth_place', ''],
        ]);
        $this->service->updateArchive((int) $id, $data);

        return $this->success(100001);
    }
}
