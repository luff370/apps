<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppAdvertisement;
use Illuminate\Database\Eloquent\Builder;

class AdvertisementDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return AppAdvertisement::class;
    }


    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', $where['status']);
        }

        return $query;
    }

}
