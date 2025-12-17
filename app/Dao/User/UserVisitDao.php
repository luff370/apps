<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserVisit;

/**
 *
 * Class UserVisitDao
 *
 * @package App\Dao\User
 */
class UserVisitDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserVisit::class;
    }

    /**
     * 用户趋势数据
     *
     * @param $time
     * @param $type
     * @param $timeType
     * @param $str
     *
     * @return mixed
     */
    public function getTrendData($time, $type, $timeType, $str)
    {
        return $this->getModel()->newQuery()->when($type != '', function ($query) use ($type) {
            $query->where('channel_type', $type);
        })->where(function ($query) use ($time) {
            if ($time[0] == $time[1]) {
                $query->whereDay('add_time', $time[0]);
            } else {
                $time[1] = date('Y/m/d', strtotime($time[1]) + 86400);
                $query->whereTime('add_time', 'between', $time);
            }
        })->selectRaw("FROM_UNIXTIME(add_time,'$timeType') as days,$str as num")->groupBy('days')->get()->toArray();
    }

    /**
     * 用户地域数据
     *
     * @param $time
     * @param $userType
     *
     * @return mixed
     */
    public function getRegion($time, $userType)
    {
        return $this->getModel()->newQuery()->when($userType != '', function ($query) use ($userType) {
            $query->where('channel_type', $userType);
        })->where(function ($query) use ($time) {
            if ($time[0] == $time[1]) {
                $query->whereDay('add_time', $time[0]);
            } else {
                $time[1] = date('Y/m/d', strtotime($time[1]) + 86400);
                $query->whereTime('add_time', 'between', $time);
            }
        })->select('COUNT(distinct(uid)) as visitNum,province')
            ->groupBy('province')->get()->toArray();
    }

    /**
     * 根据分组获取记录条数
     *
     * @param array $where
     * @param string $group
     *
     * @return mixed
     */
    public function groupCount(array $where, string $group = 'uid')
    {
        return $this->search($where)->groupBy($group)->count();
    }
}
