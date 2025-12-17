<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\Log\ClearServices;

/**
 * 首页控制器
 * Class Clear
 *
 * @package app\admin\controller
 *
 */
class ClearController extends Controller
{
    public function __construct(ClearServices $services)
    {
        $this->service = $services;
    }

    /**
     * 刷新数据缓存
     */
    public function refresh_cache()
    {
        $this->service->refresCache();

        return $this->success(400302);
    }

    /**
     * 删除日志
     */
    public function delete_log()
    {
        $this->service->deleteLog();

        return $this->success(100002);
    }
}


