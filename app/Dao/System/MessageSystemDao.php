<?php

declare (strict_types = 1);

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\MessageSystem;

/**
 *
 * Class MessageSystemDao
 *
 * @package App\Dao\System
 */
class MessageSystemDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return MessageSystem::class;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getMessageList(array $where, string $field = '*', int $page = 0, $limit = 0)
    {
        return $this->getModel()->newQuery()->where($where)->select($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->orderByRaw('add_time desc')->get()->toArray();
    }
}
