<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserMoney;

class UserMoneyDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserMoney::class;
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
    public function getList(array $where, int $page = 0, int $limit = 0)
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 余额趋势
     *
     * @param $time
     * @param $timeType
     * @param $field
     * @param $str
     *
     * @return mixed
     */
    public function getBalanceTrend($time, $timeType, $field, $str, $orderStatus = '')
    {
        return $this->getModel()->newQuery()->where(function ($query) use ($field, $orderStatus) {
            if ($orderStatus == 'add') {
                $query->where('pm', 1);
            } elseif ($orderStatus == 'sub') {
                $query->where('pm', 0);
            }
        })->where(function ($query) use ($time, $field) {
            if ($time[0] == $time[1]) {
                $query->whereDay($field, $time[0]);
            } else {
                $query->whereTime($field, 'between', $time);
            }
        })->selectRaw("FROM_UNIXTIME($field,'$timeType') as days,$str as num")->groupBy('days')->get()->toArray();
    }

    /**
     * 获取某个字段总和
     *
     * @param array $where
     * @param string $field
     *
     * @return float
     */
    public function getWhereSumField(array $where, string $field)
    {
        return $this->search($where)
            ->when(isset($where['timeKey']), function ($query) use ($where) {
                $query->whereBetweenTime('add_time', $where['timeKey']['start_time'], $where['timeKey']['end_time']);
            })
            ->sum($field);
    }

    /**
     * 根据某字段分组查询
     *
     * @param array $where
     * @param string $field
     * @param string $group
     *
     * @return array
     */
    public function getGroupField(array $where, string $field, string $group)
    {
        return $this->search($where)
            ->when(isset($where['timeKey']), function ($query) use ($where, $field, $group) {
                $query->whereBetweenTime('add_time', $where['timeKey']['start_time'], $where['timeKey']['end_time']);
                if ($where['timeKey']['days'] == 1) {
                    $timeUinx = "%H";
                } elseif ($where['timeKey']['days'] == 30) {
                    $timeUinx = "%Y-%m-%d";
                } elseif ($where['timeKey']['days'] == 365) {
                    $timeUinx = "%Y-%m";
                } elseif ($where['timeKey']['days'] > 1 && $where['timeKey']['days'] < 30) {
                    $timeUinx = "%Y-%m-%d";
                } elseif ($where['timeKey']['days'] > 30 && $where['timeKey']['days'] < 365) {
                    $timeUinx = "%Y-%m";
                }
                $query->selectRaw("sum($field) as number,FROM_UNIXTIME($group, '$timeUinx') as time");
                $query->groupBy("FROM_UNIXTIME($group, '$timeUinx')");
            })
            ->orderByRaw('add_time ASC')->get()->toArray();
    }
}
