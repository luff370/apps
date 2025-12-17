<?php

namespace App\Dao\Other;

use App\Dao\BaseDao;
use App\Models\UserCategory;

/**
 * 分类
 * Class CategoryDao
 *
 * @package App\Dao\Other
 */
class CategoryDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserCategory::class;
    }

    /**
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param array $field
     *
     * @return array
     */
    public function getCateList(array $where, int $page = 0, int $limit = 0, array $field = ['*'])
    {
        return $this->search($where)->when($page, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->field($field)->order('sort DESC,id DESC')->get()->toArray();
    }

    /**
     * 获取全部标签分类
     *
     * @return array
     */
    public function getAllLabel(array $where = [], array $with = [])
    {
        return $this->search($where)->when(count($with), function ($query) use ($with) {
            $query->with($with);
        })->order('sort DESC,id DESC')->get()->toArray();
    }
}
