<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\MemberProduct;
use Illuminate\Database\Eloquent\Builder;

class ProductDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return MemberProduct::class;
    }


    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['lang'])) {
            $query->where('lang', $where['lang']);
        }


        return $query;
    }

}
