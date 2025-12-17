<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserWechatUserDao;

/**
 *
 * Class UserWechatuserServices
 *
 * @package App\Services\User
 */
class UserWechatuserServices extends Service
{
    /**
     * UserWechatuserServices constructor.
     *
     * @param UserWechatUserDao $dao
     */
    public function __construct(UserWechatUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 自定义简单查询总数
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        return $this->dao->getCount($where);
    }

    /**
     * 复杂条件搜索列表
     *
     * @param array $where
     * @param string $field
     *
     * @return array
     */
    public function getWhereUserList(array $where, string $field): array
    {
        [$page, $limit] = $this->getPageValue();
        $order_string = '';
        $order_arr = ['asc', 'desc'];
        if (isset($where['now_money']) && in_array($where['now_money'], $order_arr)) {
            $order_string = 'now_money ' . $where['now_money'];
        }
        $list = $this->dao->getListByModel($where, $field, $order_string, $page, $limit);
        $count = $this->dao->getCountByWhere($where);

        return [$list, $count];
    }
}
