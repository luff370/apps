<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserExtract;

/**
 *
 * Class UserExtractDao
 *
 * @package App\Dao\User
 */
class UserExtractDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserExtract::class;
    }

    /**
     * 获取某个条件的提现总和
     *
     * @param array $where
     *
     * @return float
     */
    public function getWhereSum(array $where)
    {
        return $this->search($where)->sum('extract_price');
    }

    /**
     * 获取某些条件总数组合列表
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return mixed
     */
    public function getWhereSumList(array $where, string $field = 'extract_price', string $key = 'uid')
    {
        return $this->search($where)->groupBy($key)->pluck('sum(' . $field . ')', $key);
    }

    /**
     * 获取提现列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getExtractList(array $where, string $field = '*', int $page, int $limit)
    {
        return $this->search($where)->select($field)->with([
            'user' => function ($query) {
                $query->select('uid,nickname');
            },
        ])->page($page, $limit)->orderByRaw('id desc')->get()->toArray();
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
                $query->whereBetweenTime('add_time', $where['timeKey']['start_time'], $where['timeKey']['end_time']);
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

    /**
     * @param array $where
     * @param string $field
     *
     * @return float
     */
    public function getExtractMoneyByWhere(array $where, string $field)
    {
        return $this->search($where)->sum($field);
    }
}
