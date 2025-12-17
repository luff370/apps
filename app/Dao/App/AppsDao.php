<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\SystemApp;

class AppsDao extends BaseDao
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
