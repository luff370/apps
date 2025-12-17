<?php

namespace App\Support\Traits;

use App\Dao\BaseDao;

/**
 * 基础查询
 * Trait SearchDaoTrait
 *
 * @package App\Support\Traits
 * @mixin BaseDao
 */
trait SearchDaoTrait
{
    /**
     * 获取列表
     *
     * @param array $where
     * @param array|string[] $field
     * @param int $page
     * @param int $limit
     * @param null $sort
     * @param array $with
     *
     * @return array
     */
    public function getList(array $where = [], array $field = ['*'], int $page = 0, int $limit = 0, $sort = null, array $with = [])
    {
        return $this->search($where)->select($field)
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->when($sort, function ($query) use ($sort) {
                if (is_array($sort)) {
                    foreach ($sort as $v => $k) {
                        if (is_numeric($v)) {
                            $query->orderBy($k, 'desc');
                        } else {
                            $query->orderBy($v, $k);
                        }
                    }
                } else {
                    $query->orderBy($sort, 'desc');
                }
            })
            ->with($with)
            ->get()
            ->toArray();
    }
}
