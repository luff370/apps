<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserStoreOrderDao;

/**
 *
 * Class UserStoreOrderServices
 *
 * @package App\Services\User
 */
class UserStoreOrderServices extends Service
{
    /**
     * UserStoreOrderServices constructor.
     *
     * @param UserStoreOrderDao $dao
     */
    public function __construct(UserStoreOrderDao $dao)
    {
        $this->dao = $dao;
    }

    public function getUserSpreadCountList($uid, $orderBy = '', $keyword = '')
    {
        if ($orderBy === '') {
            $orderBy = 'u.add_time desc';
        }
        $where = [];
        $where[] = ['u.uid', 'IN', $uid];
        if (strlen(trim($keyword))) {
            $where[] = ['u.nickname|u.phone', 'LIKE', "%$keyword%"];
        }
        [$page, $limit] = $this->getPageValue();
        $field = "u.uid,u.nickname,u.avatar,from_unixtime(u.add_time,'%Y/%m/%d') as time,u.spread_time,u.spread_count as childCount,p.orderCount,p.numberCount";
        $list = $this->dao->getUserSpreadCountList($where, $field, $orderBy, $page, $limit);
        /** @var UserServices $userServices */
        $userServices = app(UserServices::class);
        foreach ($list as &$item) {
            $item['childCount'] = count($userServices->getUserSpredadUids($item['uid'], 1)) ?? 0;
        }

        return $list;
    }
}
