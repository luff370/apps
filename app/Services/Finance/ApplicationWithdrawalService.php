<?php

namespace App\Services\Finance;

use App\Services\Service;
use App\Dao\Finance\ApplicationWithdrawalDao;

class ApplicationWithdrawalService extends Service
{
    public function __construct(ApplicationWithdrawalDao $dao)
    {
        $this->dao = $dao;
    }
}
