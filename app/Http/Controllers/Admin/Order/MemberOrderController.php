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
        $data = $this->service->getAllByPage($args, ['*'], ['id' => 'desc'], ['app', 'user', 'product']);

        return $this->success($data);
    }

}
