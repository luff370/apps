<?php

namespace App\Dao\System\Config;

use App\Dao\BaseDao;
use App\Models\SystemStorage;
use App\Support\Traits\SearchDaoTrait;

/**
 * Class SystemStorageDao
 *
 * @package App\Dao\System\Config
 */
class SystemStorageDao extends BaseDao
{
    use SearchDaoTrait;

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return SystemStorage::class;
    }

    /**
     * @param array $where
     *
     * @return \App\Models\Model|mixed|\Illuminate\Database\Eloquent\Model
     */
    public function search(array $where = []): \Illuminate\Database\Eloquent\Builder
    {
        return $this->getModel()->newQuery()
            ->when(isset($where['type']), function ($query) use ($where) {
                $query->where('type', $where['type']);
            })->where('is_delete', 0)
            ->when(isset($where['access_key']), function ($query) use ($where) {
                $query->where('access_key', $where['access_key']);
            });
    }
}
