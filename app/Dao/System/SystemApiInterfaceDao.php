<?php

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemApiInterface;
use Illuminate\Database\Eloquent\Builder;

class SystemApiInterfaceDao extends BaseDao
{
    protected function setModel(): string
    {
        return SystemApiInterface::class;
    }

    public function search(array $where = []): Builder
    {
        $query = parent::search($where);

        if (!empty($where['module'])) {
            $query->where('module', $where['module']);
        }
        if (!empty($where['path'])) {
            $query->where('path', ltrim($where['path'], '/'));
        }
        if (!empty($where['method'])) {
            $query->where('method', strtoupper($where['method']));
        }
        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', intval($where['is_enable']));
        }

        return $query;
    }
}
