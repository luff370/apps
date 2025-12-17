<?php

declare (strict_types = 1);

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemUserLevel;

/**
 *
 * Class SystemUserLevelDao
 *
 * @package App\Dao\System
 */
class SystemUserLevelDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemUserLevel::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     *
     * @return array
     */
    public function getList(array $where, string $field = '*', int $page = 0, $limit = 0)
    {
        return $this->getModel()->newQuery()->where($where)->select($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('grade asc')->get()->toArray();
    }

    /**
     * 获取上一个用户等级
     *
     * @param $grade
     * @param string $field
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getPreLevel($grade, string $field = '*')
    {
        return $this->getModel()->newQuery()->where('grade', '<', $grade)->where('is_del', 0)->select($field)->orderByRaw('grade desc')->first();
    }

    /**
     * 获取下一个用户等级
     *
     * @param $grade
     * @param string $field
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getNextLevel($grade, string $field = '*')
    {
        return $this->getModel()->newQuery()->where('grade', '>', $grade)->where('is_del', 0)->select($field)->orderByRaw('grade asc')->first();
    }
}
