<?php

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemMenu;

/**
 * 菜单dao层
 * Class SystemMenuDao
 *
 * @package App\Dao\System
 */
class SystemMenuDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemMenu::class;
    }

    /**
     * 获取权限菜单列表
     *
     * @param array $where
     * @param array|null $field
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getMenusRole(array $where, ?array $field = [])
    {
        if (!$field) {
            $field = ['id', 'menu_name', 'icon', 'pid', 'sort', 'menu_path', 'is_show', 'header', 'is_header', 'is_show_path'];
        }

        return $this->search($where)->select($field)->orderByRaw('sort DESC,id DESC')->get();
    }

    /**
     * 获取菜单中的唯一权限
     *
     * @param array $where
     *
     * @return array
     */
    public function getMenusUnique(array $where): array
    {
        return $this->search($where)->where('unique_auth', '<>', '')->pluck('unique_auth')->toArray();
    }

    /**
     * 根据访问地址获得菜单名
     *
     * @param string $rule
     *
     * @return mixed
     */
    public function getVisitName(string $rule)
    {
        return $this->search(['url' => $rule])->value('menu_name');
    }

    /**
     * 获取后台菜单列表并分页
     *
     * @param array $where
     */
    public function getMenusList(array $where)
    {
        $where = array_merge($where, ['is_del' => 0]);

        return $this->search($where)->orderByRaw('sort DESC,id ASC')->get();
    }

    /**
     * 菜单总数
     *
     * @param array $where
     *
     * @return int
     */
    public function countMenus(array $where): int
    {
        $where = array_merge($where, ['is_del' => 0]);

        return $this->count($where);
    }

    /**
     * 指定条件获取某些菜单的名称以数组形式返回
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function column(array $where, string $field, string $key): array
    {
        return $this->search($where)->pluck($field, $key)->toArray();
    }

    /**菜单列表
     *
     * @param array $where
     * @param int $type
     */
    public function menusSelect(array $where, $type = 1)
    {
        if ($type == 1) {
            return $this->search($where)->select('id,pid,menu_name,menu_path,unique_auth,sort')->orderByRaw('sort DESC')->get();
        } else {
            return $this->search($where)->groupBy('pid')->pluck('pid');
        }
    }

    /**
     * 搜索列表
     */
    public function getSearchList()
    {
        return $this->search(['is_show' => 1, 'auth_type' => 1, 'is_del' => 0, 'is_show_path' => 0])
            ->selectRaw('id,pid,menu_name,menu_path,unique_auth,sort')->orderByRaw('sort DESC')->get();
    }
}
