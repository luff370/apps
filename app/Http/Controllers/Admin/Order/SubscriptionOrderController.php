<?php

namespace App\Http\Controllers\Admin\Order;

use App\Services\Order\SubscriptionOrderService;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Controller;

class SubscriptionOrderController extends Controller
{
    public function __construct(SubscriptionOrderService $service)
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
