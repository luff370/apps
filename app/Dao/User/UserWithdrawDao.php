<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserWithdrawal;

/**
 * 用户提现申请表
 * Class ExpressDao
 *
 * @package App\Dao\Other
 */
class UserWithdrawDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserWithdrawal::class;
    }

}
