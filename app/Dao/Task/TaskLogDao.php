<?php

namespace App\Dao\Task;

use App\Dao\BaseDao;
use App\Models\TaskLog;

class TaskLogDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return TaskLog::class;
    }

}
