<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserBill;
use App\Models\Order\StoreOrder;

/**
 *
 * Class UserBillStoreOrderDao
 *
 * @package App\Dao\User
 */
class UserBillStoreOrderDao extends BaseDao
{
    protected $alias = '';

    protected $join_alis = '';

    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserBill::class;
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
    public function getModel(string $table = '', string $alias = 'b', string $join_alias = 'o', $join = 'left')
    {
        $this->alias = $alias;
        $this->join_alis = $join_alias;
        if (!$table) {
            /** @var StoreOrder $storeOrder */
            $storeOrder = app($this->joinModel());
            $table = $storeOrder->getName();
        }

        return parent::getModel()->join($table . ' ' . $join_alias, $alias . '.link_id = ' . $join_alias . '.id', $join)->alias($alias);
    }

    /**
     * 时间分组
     *
     * @param array $where
     * @param array $orWhere
     * @param string $field
     * @param string $group
     * @param $page
     * @param $limit
     *
     * @return mixed
     */
    public function getList(array $where, array $orWhere, array $times, string $field, $page, $limit)
    {
        return $this->getModel()->newQuery()->where($where)->where("FROM_UNIXTIME(b.add_time, '%Y-%m')", 'in', $times)
            ->where(function ($q) use ($orWhere) {
                $q->orWhere($orWhere);
            })
            ->with([
                'user' => function ($query) {
                    $query->select('uid,avatar,nickname')->bind(['avatar' => 'avatar', 'nickname' => 'nickname']);
                },
            ])->select($field)->orderByRaw('id desc')->page($page, $limit)->get()->toArray();
    }

    /**
     * 时间分组
     *
     * @param array $where
     * @param array $orWhere
     * @param string $field
     * @param string $group
     * @param $page
     * @param $limit
     *
     * @return mixed
     */
    public function getListBygroupBy(array $where, array $orWhere, string $field, string $group, $page, $limit)
    {
        return $this->getModel()->newQuery()->where($where)->where(function ($q) use ($orWhere) {
            $q->orWhere($orWhere);
        })->select($field)->orderByRaw($group . ' desc')->groupBy($group)->page($page, $limit)->get()->toArray();
    }

    /**
     * 时间分组
     *
     * @param array $where
     * @param array $orWhere
     * @param string $field
     * @param string $group
     * @param $page
     * @param $limit
     *
     * @return mixed
     */
    public function getListCount(array $where, array $orWhere)
    {
        return $this->getModel()->newQuery()->where($where)->where(function ($q) use ($orWhere) {
            $q->orWhere($orWhere);
        })->count('b.id');
    }
}
