<?php

namespace App\Dao\System\Store;

use App\Dao\BaseDao;
use App\Models\SystemStoreStaff;

/**
 * 门店店员
 * Class SystemStoreStaffDao
 *
 * @package App\Dao\System\Store
 */
class SystemStoreStaffDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemStoreStaff::class;
    }

    /**
     * 获取店员列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getStoreStaffList(array $where, int $page, int $limit)
    {
        return $this->search($where)->with(['store', 'user'])->page($page, $limit)->orderByRaw('add_time DESC')->get()->toArray();
    }
}
