<?php

namespace App\Dao\System\Log;

use App\Dao\BaseDao;
use App\Models\SystemLog;

/**
 * 系统日志
 * Class SystemLogDao
 *
 * @package App\Dao\System\Log
 */
class SystemLogDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemLog::class;
    }

    /**
     * 删除过期日志
     *
     * @throws \Exception
     */
    public function deleteLog()
    {
        $this->getModel()->newQuery()->where('add_time', '<', time() - 7776000)->delete();
    }

    /**
     * 获取系统日志列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getLogList(array $where, int $page, int $limit)
    {
        return $this->search($where)->page($page, $limit)->orderByRaw('add_time DESC')->get()->toArray();
    }
}
