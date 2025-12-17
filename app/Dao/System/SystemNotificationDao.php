<?php

declare (strict_types = 1);

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemNotification;

/**
 *
 * Class SystemUserLevelDao
 *
 * @package App\Dao\System
 */
class SystemNotificationDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemNotification::class;
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
        })->orderByRaw('id asc')->get()->toArray();
    }
}
