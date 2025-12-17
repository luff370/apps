<?php

namespace App\Dao\System\Config;

use App\Dao\BaseDao;
use App\Models\SystemConfigTab;

/**
 * 配置分类
 * Class SystemConfigTabDao
 *
 * @package App\Dao\System\Config
 */
class SystemConfigTabDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemConfigTab::class;
    }

    /**
     * 获取配置分类
     *
     * @param array $where
     * @param array $field
     *
     * @return array
     */
    public function getConfigTabAll(array $searchWhere, array $field = ['*'], array $where = [])
    {
        return $this->search($searchWhere)->when(count($where), function ($query) use ($where) {
            $query->where($where);
        })->select($field)
            ->orderByRaw('sort desc,id asc')
            ->get()
            ->toArray();
    }

    /**
     * 配置分类列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     */
    public function getConfigTabList(array $where, int $page, int $limit)
    {
        return $this->search($where)->orderByRaw('sort desc,id asc')->get();
    }
}
