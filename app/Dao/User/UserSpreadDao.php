<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserSpread;

/**
 * Class UserSpreadDao
 *
 * @package App\Dao\User
 */
class UserSpreadDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserSpread::class;
    }

    /**
     * 获取推广列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where, string $field = '*', int $page = 0, $limit = 0)
    {
        return $this->search($where)->select($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('spread_time desc,d desc')->get()->toArray();
    }

    /**
     * 获取推广uids
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getSpreadUids(array $where)
    {
        return $this->search($where)->orderByRaw('spread_time desc,id desc')->pluck('uid');
    }
}
