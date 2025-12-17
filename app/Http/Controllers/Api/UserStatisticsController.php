<?php

namespace App\Http\Controllers\Api;

use App\Support\Traits\ServicesTrait;

class UserStatisticsController extends Controller
{
    use ServicesTrait;

    public function userActiveStat()
    {
        $this->userStatisticsService()->userActiveStat($this->getUuid(), $this->getAppId());

        return $this->success();
    }


}
