<?php

namespace App\Dao\System;

use App\Dao\BaseDao;
use App\Models\SystemPayment;

class PaymentDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemPayment::class;
    }

}
