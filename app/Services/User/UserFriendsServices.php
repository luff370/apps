<?php

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserFriendsDao;

/**
 * 获取好友列表
 * Class UserFriendsServices
 *
 * @package App\Services\User
 */
class UserFriendsServices extends Service
{
    public function __construct(UserFriendsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取好友列表
     *
     * @param array $where
     * @param array $with
     */
    public function getFriendList(array $where, array $with = [])
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getFriendList($where, $page, $limit, $with);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 保存好友关系
     *
     * @param array $data
     *
     * @return bool|mixed
     */
    public function saveFriend(array $data)
    {
        $userFriend = $this->dao->get(['uid' => $data['uid']]);
        if ($userFriend) {
            return true;
        } else {
            return $this->dao->save($data);
        }
    }
}
