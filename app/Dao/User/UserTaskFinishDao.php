<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserTaskFinish;

/**
 *
 * Class UserTaskFinishDao
 *
 * @package App\Dao\User
 */
class UserTaskFinishDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserTaskFinish::class;
    }
}
