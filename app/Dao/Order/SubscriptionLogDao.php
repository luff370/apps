<?php

namespace App\Dao\Order;

use App\Dao\BaseDao;
use App\Models\SubscriptionLog;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionLogDao extends BaseDao
{
    /**
     * @return string
     */
    public function setModel(): string
    {
        return SubscriptionLog::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['original_transaction_id'])) {
            $query->where('original_transaction_id', $where['original_transaction_id']);
        }

        if (!empty($where['transaction_id'])) {
            $query->where('transaction_id', $where['transaction_id']);
        }

        if (!empty($where['status'])) {
            $query->where('status', $where['status']);
        }

        if (!empty($where['user_id'])) {
            $query->where('user_id', $where['user_id']);
        }


        return $query;
    }
}
