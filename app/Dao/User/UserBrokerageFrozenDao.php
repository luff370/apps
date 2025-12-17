<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserBrokerageFrozen;

/**
 * 佣金冻结
 * Class UserBrokerageFrozenDao
 *
 * @package App\Dao\User
 */
class UserBrokerageFrozenDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserBrokerageFrozen::class;
    }

    /**
     * 搜索
     *
     * @param array $where
     *
     * @return \App\Models\Model|mixed|\Illuminate\Database\Eloquent\Model
     */
    public function search(array $where = []): \Illuminate\Database\Eloquent\Builder
    {
        return parent::search($where)->when(isset($where['isFrozen']), function ($query) use ($where) {
            if ($where['isFrozen']) {
                $query->where('frozen_time', '>', time());
            } else {
                $query->where('frozen_time', '<=', time());
            }
        });
    }

    /**
     * 获取某个账户下的冻结佣金
     *
     * @param int $uid
     * @param bool $isFrozen 获取冻结之前或者冻结之后的总金额
     *
     * @return array
     */
    public function getUserFrozenPrice(int $uid, bool $isFrozen = true)
    {
        return $this->search(['uid' => $uid, 'status' => 1, 'isFrozen' => $isFrozen])->pluck('price', 'id');
    }

    /**
     * 修改佣金冻结状态
     *
     * @param string $orderId
     *
     * @return \App\Models\Model
     */
    public function updateFrozen(string $orderId)
    {
        return $this->search(['order_id' => $orderId, 'isFrozen' => true])->update(['status' => 0]);
    }

    /**
     * 获取用户的冻结佣金数组
     *
     * @return mixed
     */
    public function getFrozenBrokerage()
    {
        return $this->getModel()->newQuery()->where('frozen_time', '>', time())
            ->where('status', 1)
            ->groupBy('uid')
            ->pluck('SUM(price) as sum_price', 'uid');
    }

    /**
     * @param $uids
     *
     * @return float
     */
    public function getSumFrozenBrokerage($uids)
    {
        return $this->getModel()->newQuery()->whereIn('uid', $uids)->where('frozen_time', '>', time())->sum('price');
    }
}
