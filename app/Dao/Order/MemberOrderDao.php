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
            if ($where['pay_status'] === 'refunded') {
                $query->where('refund_status', MemberOrder::REFUND_STATUS_REFUNDED);
            } elseif ($where['pay_status'] === 'partial_refunded') {
                $query->where('refund_status', MemberOrder::REFUND_STATUS_PARTIAL);
            } else {
                $query->where('pay_status', $where['pay_status']);
            }
        }

        if (!empty($where['member_status'])) {
            $query->where('member_status', $where['member_status']);
        }

        if (!empty($where['subscribe_status'])) {
            $query->where('subscribe_status', $where['subscribe_status']);
        }

        if (!empty($where['market_channel'])) {
            $query->whereIn('market_channel', \App\Models\SystemApp::marketChannelAliases((string) $where['market_channel']));
        }

        if (isset($where['is_repurchase']) && $where['is_repurchase'] !== '') {
            $earlierPaid = function ($earlier) {
                $earlier->selectRaw('1')
                    ->from('member_orders as earlier')
                    ->whereColumn('earlier.user_id', 'member_orders.user_id')
                    ->where('earlier.pay_status', MemberOrder::PAY_STATUS_PAID)
                    ->where(function ($time) {
                        $time->whereColumn('earlier.pay_time', '<', 'member_orders.pay_time')
                            ->orWhere(function ($sameTime) {
                                $sameTime->whereColumn('earlier.pay_time', 'member_orders.pay_time')
                                    ->whereColumn('earlier.id', '<', 'member_orders.id');
                            });
                    });
            };
            if ((string) $where['is_repurchase'] === '1') {
                $query->where('pay_status', MemberOrder::PAY_STATUS_PAID)->whereExists($earlierPaid);
            } else {
                $query->where(function (Builder $query) use ($earlierPaid) {
                    $query->where('pay_status', '<>', MemberOrder::PAY_STATUS_PAID)
                        ->orWhereNotExists($earlierPaid);
                });
            }
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string) $where['keyword']);
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('user_id', 'like', "{$keyword}%")
                    ->orWhere('order_no', 'like', "{$keyword}%")
                    ->orWhere('trade_no', 'like', "{$keyword}%")
                    ->orWhere('subscribe_product_id', 'like', "{$keyword}%")
                    ->orWhere('product_name', 'like', "{$keyword}%")
                    ->orWhere('version', 'like', "{$keyword}%");
            });
        }

        if (!empty($where['time'])) {
            $query = $this->searchDate($query, 'created_at', $where['time']);
        }

        return $query;
    }
}
