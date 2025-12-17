<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Models\User\UserWithdraw;
use App\Http\Controllers\Admin\Controller;
use App\Models\Distribution\SpreadUser;
use App\Services\User\UserWithdrawServices;
use App\Services\Order\OrderSpreadBrokerageServices;

/**
 * Class Finance
 *
 * @package App\Http\Controllers\Admin\Finance
 */
class BrokerageRecordsController extends Controller
{
    /**
     * 佣金记录
     */
    public function index(OrderSpreadBrokerageServices $services)
    {
        $filter = $this->getMore([
            ['page', 1],
            ['limit', 15],
            ['settlement_id', ''],
        ]);

        return $this->success($services->getAllByPage($filter));
    }

    /**
     * 佣金统计
     */
    public function stat(OrderSpreadBrokerageServices $services)
    {
        $filter = $this->getMore([
            ['settlement_id', ''],
        ]);

        return $this->success($services->getStatData($filter));
    }

    /**
     * 佣金提现记录列表
     */
    public function withdraw(UserWithdrawServices $services)
    {
        $filter = $this->getMore([
            ['keyword', ''],
        ]);

        $filter['status'] = UserWithdraw::STATUS_SUCCESS;
        $filter['user_type'] = SpreadUser::TypeAgent;
        $data = $services->getAllByPage($filter);

        return $this->success($data);
    }
}
