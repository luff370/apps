<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserAccessLogService;

/**
 * UserAccessLogController
 */
class UserAccessLogController extends Controller
{
    public function __construct(UserAccessLogService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['user_id', ''],
            ['app_id', ''],
            ['market_channel', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter,['*'],['id'=>'desc'],['user']);

        return $this->success($data);
    }

}
