<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\User;

/**
 *
 * Class UserAuthDao
 *
 * @package App\Dao\User
 */
class UserAuthDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return User::class;
    }
}
