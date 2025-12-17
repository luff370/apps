<?php

namespace App\Dao\Sms;

use App\Dao\BaseDao;
use App\Models\SystemConfig;

/**
 * 短信dao
 * Class SmsAdminDao
 *
 * @package App\Dao\Sms
 */
class SmsAdminDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemConfig::class;
    }
}
