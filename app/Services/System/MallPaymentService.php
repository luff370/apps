<?php

namespace App\Services\System;

use App\Services\Service;
use App\Dao\System\MallPaymentDao;

/**
 * Class MallPaymentService
 *
 * @package App\Services\System\admin
 */
class MallPaymentService extends Service
{
    /**
     * StoreBrandServices constructor.
     */
    public function __construct(MallPaymentDao $dao)
    {
        $this->dao = $dao;
    }

}
