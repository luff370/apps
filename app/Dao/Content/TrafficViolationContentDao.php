<?php

namespace App\Dao\Content;

use App\Dao\BaseDao;
use App\Models\SystemApp;
use App\Models\TrafficViolationContent;
use Illuminate\Database\Eloquent\Builder;

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
