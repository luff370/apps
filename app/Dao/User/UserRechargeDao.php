<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserRecharge;

/**
 *
 * Class UserRechargeDao
 *
 * @package App\Dao\User
 */
class UserRechargeDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserRecharge::class;
    }

    /**
     * 获取充值记录
     *
     * @param array $where
     * @param string $filed
     * @param int $page
     * @param int $limit
     */
    public function getList(array $where, string $filed = "*", int $page, int $limit)
    {
        return $this->search($where)->select($filed)->with([
            'user' => function ($query) {
                $query->select('uid,phone,nickname,avatar');
            },
        ])->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('id desc')->get()->toArray();
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
                $query->whereBetweenTime('pay_time', $where['timeKey']['start_time'], $where['timeKey']['end_time']);
            })
            ->sum($field);
    }

    /**根据某字段分组查询
     *
     * @param array $where
     * @param string $field
     * @param string $group
     *
     * @return mixed
     */
    public function getGroupField(array $where, string $field, string $group)
    {
        return $this->search($where)
            ->when(isset($where['timeKey']), function ($query) use ($where, $field, $group) {
                $query->whereBetweenTime('pay_time', $where['timeKey']['start_time'], $where['timeKey']['end_time']);
                $timeUinx = "%H";
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

    public function getTrendData($time, $type, $timeType)
    {
        return $this->getModel()->newQuery()->when($type != '', function ($query) use ($type) {
            $query->where('channel_type', $type);
        })->where(function ($query) use ($time) {
            if ($time[0] == $time[1]) {
                $query->whereDay('pay_time', $time[0]);
            } else {
                $time[1] = date('Y/m/d', strtotime($time[1]) + 86400);
                $query->whereTime('pay_time', 'between', $time);
            }
        })->selectRaw("FROM_UNIXTIME(pay_time,'$timeType') as days,count(id) as num")
            ->groupBy('days')->get()->toArray();
    }
}
