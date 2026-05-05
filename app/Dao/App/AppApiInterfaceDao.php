<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppApiInterface;
use Illuminate\Database\Eloquent\Builder;

class AppApiInterfaceDao extends BaseDao
{
    protected function setModel(): string
    {
        return AppApiInterface::class;
    }

    public function search(array $where = []): Builder
    {
        $query = parent::search($where);

        if (isset($where['app_id']) && $where['app_id'] !== '') {
            $query->where('app_id', intval($where['app_id']));
        }
        if (!empty($where['package_name'])) {
            $query->where('package_name', $where['package_name']);
        }
        if (!empty($where['module'])) {
            $query->where('module', $where['module']);
        }
        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', intval($where['is_enable']));
        }

        return $query;
    }
}

