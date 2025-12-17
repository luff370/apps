<?php

namespace App\Dao\Task;

use App\Dao\BaseDao;
use App\Models\Task;

class TaskDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return Task::class;
    }

}
