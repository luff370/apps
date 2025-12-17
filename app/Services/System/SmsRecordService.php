<?php

namespace App\Services\System;

use App\Services\Service;
use App\Dao\Sms\SmsRecordDao;

class SmsRecordService extends Service
{
    public function __construct(SmsRecordDao $dao)
    {
        $this->dao = $dao;
    }
}
