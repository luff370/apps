<?php

namespace App\Dao\ChatAI;

use App\Dao\BaseDao;
use App\Models\AiTaskLog;

class AiTaskLogDao extends BaseDao
{
    /**
     * 获取当前模型
     *
     * @return string
     */
     protected function setModel():string
     {
         return AiTaskLog::class;
     }
}
