<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserBrokerage;

class UserBrokerageDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserBrokerage::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     * @param array $typeWhere
     *
     * @return array
     */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0, array $typeWhere = [])
    {
        return $this->search($where)->when(count($typeWhere) > 0, function ($query) use ($typeWhere) {
            $query->where($typeWhere);
        })->select($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 查询列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getUserBrokerageList(array $where)
    {
        return $this->search($where)->get()->toArray();
    }

    /**
     * 获取佣金排行
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function brokerageRankList(array $where, int $page = 0, int $limit = 0)
    {
        return $this->search($where)->select('uid,SUM(IF(pm=1,`number`,-`number`)) as brokerage_price')->with([
            'user' => function ($query) {
                $query->select('uid,avatar,nickname');
            },
        ])->orderByRaw('brokerage_price desc')->groupBy('uid')->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->get()->toArray();
    }

    /**
     * 获取某些条件的佣金总数
     *
     * @param array $where
     *
     * @return mixed
     */
    public function getBrokerageSumColumn(array $where)
    {
        if (isset($where['uid']) && is_array($where['uid'])) {
            return $this->search($where)->groupBy('uid')->pluck('sum(number) as num', 'uid');
        } else {
            return $this->search($where)->sum('number');
        }
    }

    /**
     * 获取某个账户下的冻结佣金
     *
     * @param int $uid
     *
     * @return float
     */
    public function getUserFrozenPrice(int $uid)
    {
        return $this->search(['uid' => $uid, 'status' => 1, 'pm' => 1])->where('frozen_time', '>', time())->sum('number');
    }
}
