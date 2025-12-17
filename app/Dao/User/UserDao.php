<?php

declare (strict_types=1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 用户
 * Class UserDao
 *
 * @package App\Dao\User
 */
class UserDao extends BaseDao
{
    protected function setModel(): string
    {
        return User::class;
    }

    /**
     * 获取某些条件总数
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        return $this->search($where)->count();
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (isset($where['is_reg']) && is_numeric($where['is_reg'])) {
            $query->where('is_reg', $where['is_reg']);
        }

        if (!empty($where['status']) && is_numeric($where['status'])) {
            $query->where('status', $where['status']);
        }

        if (!empty($where['is_vip']) && is_numeric($where['is_vip'])) {
            $query->where('is_vip', $where['is_vip']);
        }

        if (!empty($where['is_charge']) && is_numeric($where['is_charge'])) {
            $query->where('total_charge', '>', 0);
        }

        if (!empty($where['country'])) {
            if ($where['country'] == 'domestic') {
                $query->where('region', 'like', '中国%');
            } else {
                $query->where('region', 'not like', '中国%');
            }
        }

        if (isset($where['ids']) && is_array($where['ids'])) {
            $query->whereIn('id', $where['ids']);
        }

        if (!empty($where['user_time'])) {
            $this->searchTime($query, 'reg_time', $where['user_time']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('id', $where['keyword'])
                    ->orWhere('account', $where['keyword'])
                    // ->orWhere('nickname', $where['keyword'])
                    ->orWhere('uuid', $where['keyword'])
                    ->orWhere('device_sn', $where['keyword'])
                    ->orWhere('phone', $where['keyword']);
            });
        }

        return $query;
    }

    /**
     * 获取用户列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where, string $field = '*', int $page = 0, int $limit = 0): array
    {
        return $this->search($where)
            ->selectRaw($field)
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /**
     * 用户支付成功个数增加
     *
     * @param int $id
     *
     * @return mixed
     */
    public function incPayCount(int $id)
    {
        return $this->getModel()->newQuery()->where('id', $id)->inc('pay_count', 1)->update();
    }

    /**
     * 某个字段累加某个数值
     *
     * @param string $field
     * @param int $num
     */
    public function incField(int $id, string $field, int $num = 1)
    {
        return $this->getModel()->newQuery()->where('id', $id)->inc($field, $num)->update();
    }

    /**
     * @param $id
     *




     */
    public function getUserLabel($id, $field = '*')
    {
        return $this->search(['id' => $id])->select($field)->with(['label'])->get()->toArray();
    }

    /**
     * 获取分销用户
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getAgentUserList(array $where, string $field = '*', int $page, int $limit)
    {
        return $this->search($where)->select($field)->with([
            'extract' => function ($query) {
                $query->select('sum(extract_price) as extract_count_price,count(id) as extract_count_num,id')->where('status', '1')->groupBy('id');
            },
            'order' => function ($query) {
                $query->select('sum(pay_price) as order_price,count(id) as order_count,id')->where('paid', 1)->where('refund_status', 0)->groupBy('id');
            },
            'bill' => function ($query) {
                $query->select('sum(number) as brokerage_money,id')->where('category', 'now_money')->where('type', 'brokerage')->where('status', 1)->where('pm', 1)->groupBy('id');
            },
            'spreadCount' => function ($query) {
                $query->select('count(*) as spread_count,spread_id')->groupBy('spread_id');
            },
            'spreadUser' => function ($query) {
                $query->select('id,phone,nickname');
            },
            'agentLevel' => function ($query) {
                $query->select('id,name');
            },
        ])->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 获取推广人列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getSairList(array $where, string $field = '*', int $page, int $limit)
    {
        return $this->search($where)->select($field)->with([
            'order' => function ($query) {
                $query->select('sum(pay_price) as order_price,count(id) as order_count,id')->where('paid', 1)->where('pid', '<=', 0)->where('refund_status', 0)->groupBy('id');
            },
            'spreadCount' => function ($query) {
                $query->select('count(*) as spread_count,spread_id')->groupBy('spread_id');
            },
            'spreadUser' => function ($query) {
                $query->select('id,phone,nickname');
            },
        ])->page($page, $limit)->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 获取推广人排行
     *
     * @param array $time
     * @param string $field
     * @param int $page
     * @param int $limit
     */
    public function getAgentRankList(array $time, string $field = '*', int $page, int $limit)
    {
        return $this->getModel()->newQuery()->alias('t0')
            ->select($field)
            ->join('user t1', 't0.id = t1.spread_id', 'LEFT')
            ->where('t1.spread_id', '<>', 0)
            ->orderByRaw('count desc')
            ->orderByRaw('t0.id desc')
            ->where('t1.spread_time', 'BETWEEN', $time)
            ->page($page, $limit)
            ->groupBy('t0.id')
            ->get()->toArray();
    }

    /**
     * 获取推广员ids
     *
     * @param array $where
     *
     * @return array
     */
    public function getAgentUserIds(array $where)
    {
        return $this->search($where)->pluck('id');
    }

    /**
     * 某个条件 用户某个字段总和
     *
     * @param array $where
     * @param string $filed
     *
     * @return float
     */
    public function getWhereSumField(array $where, string $filed)
    {
        return $this->search($where)->sum($filed);
    }

    /**
     * 根据条件查询对应的用户信息以数组形式返回
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getUserInfoArray(array $where, string $field, string $key)
    {
        return $this->search($where)->pluck($field, $key);
    }

    /**
     * 获取特定时间用户访问量
     *
     * @param $time
     * @param $week
     *
     * @return int
     */
    public function todayLastVisit($time, $week)
    {
        switch ($week) {
            case 1:
                return $this->search(['last_time' => $time ?: 'today'])->count();
            case 2:
                return $this->search(['last_time' => $time ?: 'week'])->count();
        }
    }

    /**
     * 获取特定时间用户访问量
     *
     * @param $time
     * @param $week
     *
     * @return int
     */
    public function todayAddVisit($time, $week, $authSearch = [])
    {
        switch ($week) {
            case 1:
                return $this->search(array_merge(['add_time' => $time ?: 'today'], $authSearch))->count();
            case 2:
                return $this->search(array_merge(['add_time' => $time ?: 'week'], $authSearch))->count();
        }
    }

    /**
     * 获取特定时间内用户列表
     *
     * @param $starday
     * @param $yesterday
     *
     * @return mixed
     */
    public function userList($starday, $yesterday, $authSearch = [])
    {
        return $this->getModel()->newQuery()
            ->whereBetween('add_time', [$starday, $yesterday])
            ->when(!empty($authSearch), function ($query) use ($authSearch) {
                $query->where($authSearch);
            })
            ->selectRaw("FROM_UNIXTIME(add_time,'%m-%e') as day,count(*) as count")
            ->groupBy("day")
            ->orderByRaw('add_time asc')
            ->get()
            ->toArray();
    }

    /**
     * 购买量范围的用户数量
     *
     * @param $status
     *
     * @return int
     */
    public function userCount($status)
    {
        switch ($status) {
            case 1:
                return $this->getModel()->newQuery()->where('pay_count', '>', 1)->where('pay_count', '<=', 4)->count();
            case 2:
                return $this->getModel()->newQuery()->where('pay_count', '>', 4)->count();
        }
    }

    /**
     * 获取用户统计数据
     *
     * @param $time
     * @param $type
     * @param $timeType
     *
     * @return mixed
     */
    public function getTrendData($time, $type, $timeType)
    {
        return $this->getModel()->newQuery()->when($type != '', function ($query) use ($type) {
            $query->where('user_type', $type);
        })->where(function ($query) use ($time) {
            if ($time[0] == $time[1]) {
                $query->whereDay('add_time', $time[0]);
            } else {
                $time[1] = date('Y/m/d', strtotime($time[1]) + 86400);
                $query->whereTime('add_time', 'between', $time);
            }
        })->selectRaw("FROM_UNIXTIME(add_time,'$timeType') as days,count(id) as num")->groupBy('days')->get()->toArray();
    }

    /**
     * @param array $where
     *
     * @return array
     */
    public function getUserInfoList(array $where, $field = "*"): array
    {
        return $this->search($where)->select($field)->get()->toArray();
    }

    /**
     * 获取用户会员数量
     *
     * @param $where (time  type)
     *
     * @return int
     */
    public function getMemberCount($where, int $overdue_time = 0)
    {
        if (!$overdue_time) {
            $overdue_time = time();
        }

        return $this->search($where)->where('is_ever_level', 1)->orWhere(function ($qeury) use ($overdue_time) {
            $qeury->where('is_money_level', '>', 0)->where('overdue_time', '>', $overdue_time);
        })->count();
    }
}
