<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\User;
use App\Models\Order\StoreOrder;

/**
 *
 * Class UserStoreOrderDao
 *
 * @package App\Dao\User
 */
class UserStoreOrderDao extends BaseDao
{
    /**
     * @var string
     */
    protected $alias = '';

    /**
     * @var string
     */
    protected $join_alis = '';

    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return User::class;
    }

    public function joinModel(): string
    {
        return StoreOrder::class;
    }

    /**
     * 关联模型
     *
     * @param string $alias
     * @param string $join_alias
     *
     * @return \App\Models\Model
     */
    public function getModel(string $table = '', string $alias = 'u', string $join_alias = 'p', $join = 'left')
    {
        $this->alias = $alias;
        $this->join_alis = $join_alias;
        if (!$table) {
            /** @var StoreOrder $storeOrder */
            $storeOrder = app($this->joinModel());
            $table = $storeOrder->getName();
        }

        return parent::getModel()->join($table . ' ' . $join_alias, $alias . '.uid = ' . $join_alias . '.uid', $join)->alias($alias);
    }

    /**
     * 推广团队列表
     *
     * @param array $where
     * @param string $field
     * @param string $order_by
     * @param $page
     * @param $limit
     *
     * @return array
     */
    public function getUserSpreadCountList(array $where, string $field, string $order_by, int $page, int $limit)
    {
        $table = app($this->joinModel())->getModel()->where('o.paid', 1)->whereIn('o.pid', [-1, 0])->groupBy('o.uid')->select(['SUM(o.pay_price) as numberCount', 'count(o.id) as orderCount', 'o.uid', 'o.order_id'])
            ->where('o.refund_status', 0)->alias('o')->fetchSql(true)->get();

        return $this->getModel('(' . $table . ')')->where($where)->select($field)->orderByRaw($order_by)->page($page, $limit)->get()->toArray();
    }
}
