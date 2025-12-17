<?php

namespace App\Dao\System\Upgrade;

use App\Dao\BaseDao;
use App\Models\UpgradeLog;

/**
 * 升级记录dao
 * Class UpgradeLogDao
 *
 * @package App\Dao\System\Upgrade
 */
class UpgradeLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return UpgradeLog::class;
    }

    /**
     * 列表
     *
     * @param array $where
     * @param array $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $field, int $page = 0, int $limit = 0): array
    {
        return $this->search()->select($field)->page($page, $limit)->get()->toArray();
    }
}
