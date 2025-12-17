<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppPayment;
use Illuminate\Database\Eloquent\Builder;

class PaymentDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return AppPayment::class;
    }


    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        return $query;
    }

}
