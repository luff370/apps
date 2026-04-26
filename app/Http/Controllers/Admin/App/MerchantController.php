<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\MerchantService;

/**
 * MerchantController
 */
class MerchantController extends Controller
{
    public function __construct(MerchantService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['type', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter);

        return $this->success($data);
    }

    /**
     * 新增表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function create()
    {
        return $this->success($this->service->createForm());
    }

    /**
     * 保存新建
     */
    public function store()
    {
        $data = $this->getMore([
            ['name', ''],
            ['domain', ''],
            ['type', '1'],
            ['corporate', ''],
            ['registered_address', ''],
        ]);

        $this->service->save($data);

        return $this->success(100021);
    }


    /**
     * 编辑表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm($id));
    }

    /**
     * 数据更新
     *
     * @throws \App\Exceptions\AdminException
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['name', ''],
            ['domain', ''],
            ['type', '1'],
            ['corporate', ''],
            ['registered_address', ''],
        ]);
        $this->service->update($id, $data);

        return $this->success(100001);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field)
    {
       if (!$id = intval($id)) {
           return $this->fail(100100);
       }
       $this->service->update($id, [$field => $value]);

       return $this->success(100014);
    }
}
