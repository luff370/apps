<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserFeedback;

class UserFeedbackDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserFeedback::class;
    }

}
