<?php

namespace App\Services\System\Log;

use App\Services\Service;
use App\Dao\System\Log\SystemLogDao;
use App\Services\System\SystemMenuServices;
use App\Services\System\Admin\SystemAdminServices;

/**
 * 系统日志
 * Class SystemLogServices
 *
 * @package App\Services\System\Log
 * @method deleteLog() 定期删除日志
 */
class SystemLogServices extends Service
{
    /**
     * 构造方法
     * SystemLogServices constructor.
     *
     * @param SystemLogDao $dao
     */
    public function __construct(SystemLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 记录访问日志
     *
     * @param int $adminId
     * @param string $adminName
     * @param string $type
     *
     * @return bool
     */
    public function recordAdminLog(int $adminId, string $adminName, string $type)
    {
        $module = app('http')->getName();
        $rule = trim(strtolower(request()->getRequestUri()));

        /** @var SystemMenuServices $service */
        $service = app(SystemMenuServices::class);
        $data = [
            'method' => $module,
            'admin_id' => $adminId,
            'add_time' => time(),
            'admin_name' => $adminName,
            'path' => $rule,
            'page' => $service->getVisitName($rule) ?: '未知',
            'ip' => request()->ip(),
            'type' => $type,
        ];
        if ($this->dao->save($data)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取系统日志列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getLogList(array $where, int $level)
    {
        [$page, $limit] = $this->getPageValue();
        if (!$where['admin_id']) {
            /** @var SystemAdminServices $service */
            $service = app(SystemAdminServices::class);
            $where['admin_id'] = $service->getAdminIds($level);
        }
        $list = $this->dao->getLogList($where, $page, $limit);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }
}
