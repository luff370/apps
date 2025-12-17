<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserGroup;

/**
 *
 * Class UserGroupDao
 *
 * @package App\Dao\User
 */
class UserGroupDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserGroup::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where = [], string $field = '*', int $page = 0, int $limit = 0)
    {
        return $this->search($where)
            ->select($field)
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })->get()->toArray();
    }
}
