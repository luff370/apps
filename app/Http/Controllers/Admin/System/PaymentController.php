<?php

namespace App\Http\Controllers\Admin\System;

use App\Services\System\PaymentService;
use App\Http\Controllers\Admin\Controller;

class PaymentController extends Controller
{
    public function __construct(PaymentService $services)
    {
        $this->service = $services;
    }

    /**
     * 列表
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $args = $this->getMore(['type', '']);
        $data = $this->service->getAll($args);

        return $this->success($this->service->tidyListData($data));
    }

    /**
     * 保存数据
     *
     * @throws \Throwable
     */
    public function store()
    {
        $data = $this->getMore([
            ['id', 0],
            ['type', ''],
            ['name', ''],
            ['mer_id', ''],
            ['mch_id', ''],
            ['api_key', ''],
            ['serial_no', ''],
            ['private_key', ''],
            ['public_key', ''],
            ['mch_public_cert', ''],
            ['mch_root_cert', ''],
            ['is_enable', ''],
        ]);

        $this->service->save($data);

        return $this->success('保存成功');
    }

    public function show($id)
    {
        $info = $this->service->getRow($id);

        return $this->success($info);
    }

    public function setStatus($id, $isEnable)
    {
        $this->service->update($id, ['is_enable' => $isEnable]);

        return $this->success('设置成功');
    }

    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success('删除成功');
    }
}
