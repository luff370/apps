<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserFriends;

class UserFriendsDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserFriends::class;
    }

    /**
     * 获取好友关系
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getFriendList(array $where, int $page, int $limit, array $with = [])
    {
        return $this->search($where)->when($with, function ($query) use ($with) {
            $query->with($with);
        })->page($page, $limit)->get()->toArray();
    }
}
