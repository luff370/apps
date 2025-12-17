<?php

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemApp;

/**
 * 应用信息
 * Class ExpressDao
 *
 * @package App\Dao\Other
 */
class MallAppDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemApp::class;
    }

    public function search(array $where = []): \Illuminate\Database\Eloquent\Builder
    {
        return $this->getModel()->newQuery()
            ->when(isset($where['is_enable']) && $where['is_enable'] !== '', function ($query) use ($where) {
                $query->where('is_enable', $where['is_enable']);
            })->when(!empty($where['keyword']), function ($query) use ($where) {
                $query->whereLike('name', '%' . $where['keyword'] . '%');
            });
    }
}
