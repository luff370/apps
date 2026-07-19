<?php

namespace App\Http\Controllers\Admin\Order;

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Controller;
use App\Services\Order\MemberOrderService;

class MemberOrderController extends Controller
{
    public function __construct(MemberOrderService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $args = $request->all();
        $data = $this->service->getAllByPage($args, ['*'], ['id' => 'desc'], ['app', 'user']);

        return $this->success($data);
    }

    public function refund($id)
    {
        $data = $this->getMore([
            ['refund_price', 0],
            ['remark', ''],
        ]);

        $this->service->refund((int)$id, $data);

        return $this->success('退款成功');
    }

    public function remark($id)
    {
        $data = $this->getMore([
            ['remark', ''],
        ]);

        $this->service->remark((int)$id, (string)$data['remark']);

        return $this->success('备注保存成功');
    }
}
