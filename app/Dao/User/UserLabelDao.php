<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserLabel;

/**
 *
 * Class UserLabelDao
 *
 * @package App\Dao\User
 */
class UserLabelDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserLabel::class;
    }

    /**
     * 获取列表
     *
     * @param string $field
     * @param int $page
     * @param int $limit
     * @param array $where
     * @param array $field
     *
     * @return array
     */
    public function getList(int $page = 0, int $limit = 0, array $where = [], array $field = ['*']): array
    {
        return $this->search($where)->with(['cateName'])->when(isset($where['label_cate']) && $where['label_cate'], function ($query) use ($where) {
            $query->where('label_cate', $where['label_cate']);
        })->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->select($field)->get()->toArray();
    }
}
