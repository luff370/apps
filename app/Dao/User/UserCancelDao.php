<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserCancel;

class UserCancelDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return UserCancel::class;
    }

    /**
     * 获取列表
     *
     * @param $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList($where, $page = 0, $limit = 0)
    {
        return $this->search($where)->with(['user'])
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })->get()->toArray();
    }
}
