<?php

namespace App\Dao\System\Admin;

use App\Dao\BaseDao;
use App\Models\SystemRole;

/**
 * Class SystemRoleDao
 *
 * @package App\Dao\System\Admin
 */
class SystemRoleDao extends BaseDao
{
    /**
     * 设置模型名
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemRole::class;
    }

    /**
     * 获取权限
     *
     * @param string $field
     * @param string $key
     *
     * @return mixed
     */
    public function getRoule(array $where = [], ?string $field = null, ?string $key = null)
    {
        return $this->search($where)->pluck($field ?: 'role_name', $key ?: 'id')->toArray();
    }

    /**
     * 获取身份列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getRouleList(array $where, int $page, int $limit)
    {
        return $this->search($where)->offset(($page - 1) * $limit)->limit($limit)->get()->toArray();
    }
}
