<?php

namespace App\Dao\Finance;

use App\Dao\BaseDao;
use App\Models\SystemApp;
use Illuminate\Database\Eloquent\Builder;

class ApplicationWithdrawalDao extends BaseDao
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

}
