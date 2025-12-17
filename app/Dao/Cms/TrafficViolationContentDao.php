<?php

namespace App\Dao\Cms;

use App\Dao\BaseDao;
use App\Models\TrafficViolationContent;

class TrafficViolationContentDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return TrafficViolationContent::class;
    }

}
