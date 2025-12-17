<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\Log\SystemLogServices;
use App\Services\System\Admin\SystemAdminServices;

/**
 * 管理员操作记录表控制器
 * Class SystemLog
 *
 * @package App\Http\Controllers\Admin\System
 */
class SystemLogController extends Controller
{
    /**
     * 构造方法
     * SystemLog constructor.
     *
     * @param SystemLogServices $services
     */
    public function __construct(SystemLogServices $services)
    {
        $this->service = $services;
        $this->service->deleteLog();
    }

    /**
     * 显示操作记录
     */
    public function index()
    {
        $where = $this->getMore([
            ['pages', ''],
            ['path', ''],
            ['ip', ''],
            ['admin_id', ''],
            ['data', '', '', 'time'],
        ]);

        return $this->success($this->service->getLogList($where, (int) adminInfo()['level']));
    }

    public function search_admin(SystemAdminServices $services)
    {
        $info = $services->getOrdAdmin('id,real_name', adminInfo()['level']);

        return $this->success(compact('info'));
    }
}

