<?php

namespace App\Dao\Order;

use App\Dao\BaseDao;
use App\Models\MemberOrder;
use Illuminate\Database\Eloquent\Builder;

class MemberOrderDao extends BaseDao
{
    /**
     * @return string
     */
    public function setModel(): string
    {
        return MemberOrder::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['member_type'])) {
            $query->where('member_type', $where['member_type']);
        }

        if (!empty($where['pay_type'])) {
            $query->where('pay_type', $where['pay_type']);
        }

        if (isset($where['pay_status']) && $where['pay_status'] !== '') {
            $query->where('pay_status', $where['pay_status']);
        }

        if (!empty($where['member_status'])) {
            $query->where('member_status', $where['member_status']);
        }

        if (!empty($where['subscribe_status'])) {
            $query->where('subscribe_status', $where['subscribe_status']);
        }

        if (!empty($where['market_channel'])) {
            $query->where('market_channel', $where['market_channel']);
        }

        if (!empty($where['version'])) {
            $query->where('version', $where['version']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('user_id', 'like', "{$where['keyword']}%")
                    ->orWhere('order_no', 'like', "{$where['keyword']}%")
                    ->orWhere('trade_no', 'like', "{$where['keyword']}%")
                    ->orWhere('subscribe_product_id', 'like', "{$where['keyword']}%")
                    ->orWhereRaw('product_id in (select id from member_products where name = ?)', [$where['keyword']]);
            });
        }

        if (!empty($where['time'])) {
            $query = $this->searchDate($query, 'created_at', $where['time']);
        }

        return $query;
    }
}
