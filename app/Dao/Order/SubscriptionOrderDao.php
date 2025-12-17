<?php

namespace App\Dao\Order;

use App\Dao\BaseDao;
use App\Models\SubscriptionOrder;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionOrderDao extends BaseDao
{
    /**
     * @return string
     */
    public function setModel(): string
    {
        return SubscriptionOrder::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['pay_type'])) {
            $query->where('pay_type', $where['pay_type']);
        }

        if (!empty($where['status'])) {
            $query->where('status', $where['status']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('user_id', 'like', "{$where['keyword']}%")
                    ->orWhere('original_transaction_id', 'like', "{$where['keyword']}%")
                    ->orWhere('product_id', 'like', "{$where['keyword']}%");
                    // ->orWhereRaw('product_id in (select id from member_products where name = ?)', [$where['keyword']]);
            });
        }

        if (!empty($where['time'])) {
            $query = $this->searchDate($query, 'created_at', $where['time']);
        }

        return $query;
    }
}
