<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserLabelCate;

/**
 * Class UserLabelCateDao
 *
 * @package App\Dao\User
 */
class UserLabelCateDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserLabelCate::class;
    }

    /**
     * 获取标签分类列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getLabelList(array $where, int $page, int $limit)
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('sort DESC')->get()->toArray();
    }

    /**
     * 获取全部标签分类
     *
     * @return array
     */
    public function getAllLabel(array $with = [])
    {
        return $this->getModel()->newQuery()->when(count($with), function ($query) use ($with) {
            $query->with($with);
        })->orderByRaw('sort DESC')->get()->toArray();
    }
}
