<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppApiObfuscationProfile;
use Illuminate\Database\Eloquent\Builder;

class AppApiObfuscationProfileDao extends BaseDao
{
    protected function setModel(): string
    {
        return AppApiObfuscationProfile::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', intval($where['app_id']));
        }
        if (!empty($where['package_name'])) {
            $query->where('package_name', $where['package_name']);
        }

        return $query;
    }
}

