<?php

declare (strict_types = 1);

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class AppVersionDao
 *
 * @package App\Dao\System
 */
class AppVersionDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return AppVersion::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (isset($where['audit_status']) && $where['audit_status'] !== '') {
            $query->where('audit_status', $where['audit_status']);
        }

        return $query;
    }


}
