<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppApiObfuscationAlias;
use Illuminate\Database\Eloquent\Builder;

class AppApiObfuscationAliasDao extends BaseDao
{
    protected function setModel(): string
    {
        return AppApiObfuscationAlias::class;
    }

    public function search(array $where = []): Builder
    {
        $query = parent::search($where);

        if (!empty($where['profile_id'])) {
            $query->where('profile_id', intval($where['profile_id']));
        }
        if (!empty($where['interface_id'])) {
            $query->where('interface_id', intval($where['interface_id']));
        }
        if (!empty($where['alias'])) {
            $query->where('alias', $where['alias']);
        }
        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', intval($where['is_enable']));
        }

        return $query;
    }
}
