<?php

namespace App\Dao\System\Admin;

use App\Dao\BaseDao;
use App\Models\SystemAdmin;

/**
 * Class SystemAdminDao
 *
 * @package App\Dao\System\Admin
 */
class SystemAdminDao extends BaseDao
{
    protected function setModel(): string
    {
        return SystemAdmin::class;
    }

    /**
     * 获取管理员列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return mixed
     */
    public function getList(array $where, int $page, int $limit)
    {
        return $this->search($where)->offset(($page - 1) * $limit)->limit($limit)->get()->toArray();
    }

    /**
     * 用管理员名查找管理员信息
     *
     * @param string $account
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function accountByAdmin(string $account)
    {
        return $this->search(['account' => $account, 'is_del' => 0, 'status' => 1])->first();
    }

    /**
     * 当前账号是否可用
     *
     * @param string $account
     * @param int $id
     *
     * @return int
     */
    public function isAccountUsable(string $account, int $id)
    {
        return $this->search(['account' => $account, 'is_del' => 0])->where('id', '<>', $id)->count();
    }

    /**
     * 获取adminid
     *
     * @param int $level
     *
     * @return array
     */
    public function getAdminIds(int $level)
    {
        return $this->getModel()->newQuery()->where('level', '>=', $level)->pluck('id', 'id')->toArray();
    }

    /**
     * 获取低于等级的管理员名称和id
     *
     * @param string $field
     * @param int $level
     *
     * @return array
     */
    public function getOrdAdmin(string $field = 'real_name,id', int $level = 0)
    {
        return $this->getModel()->newQuery()->where('level', '>=', $level)->select($field)->get()->toArray();
    }

    /**
     * 条件获取管理员数据
     *
     * @param $where
     *
     * @return mixed
     */
    public function getInfo($where)
    {
        return $this->getModel()->newQuery()->where($where)->first();
    }

    /**
     * 检测是否有管理员使用该角色
     *
     * @param int $id
     *
     * @return bool
     */
    public function checkRoleUse(int $id): bool
    {
        return (bool) $this->getModel()->newQuery()->whereFindInSet('roles', $id)->count();
    }
}
