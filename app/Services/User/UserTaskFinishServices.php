<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserTaskFinishDao;

/**
 *
 * Class UserTaskFinishServices
 *
 * @package App\Services\User
 */
class UserTaskFinishServices extends Service
{
    /**
     * UserTaskFinishServices constructor.
     *
     * @param UserTaskFinishDao $dao
     */
    public function __construct(UserTaskFinishDao $dao)
    {
        $this->dao = $dao;
    }
}
