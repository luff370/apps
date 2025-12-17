<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserSign;

/**
 *
 * Class UserSignDao
 *
 * @package App\Dao\User
 */
class UserSignDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserSign::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     */
    public function getList(array $where, string $field, int $page, int $limit)
    {
        return $this->search($where)->select($field)->orderByRaw('id desc')->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->get()->toArray();
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     */
    public function getListgroupBy(array $where, string $field, int $page, int $limit, string $group)
    {
        return $this->search($where)->select($field)->orderByRaw('id desc')->groupBy($group)->page($page, $limit)->get()->toArray();
    }
}
