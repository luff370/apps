<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserWhiteListLogService;

/**
 * UserWhitelistController
 */
class UserWhitelistLogController extends Controller
{
    public function __construct(UserWhiteListLogService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['market_channel', ''],
            ['source_type', ''],
            ['uuid', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter,['*'], ['id' => 'desc'], ['user']);

        return $this->success($data);
    }






}
