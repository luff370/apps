<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistic\CapitalFlowServices;

class CapitalFlowController extends Controller
{
    /**
     * @param CapitalFlowServices $services
     */
    public function __construct(CapitalFlowServices $services)
    {
        $this->service = $services;
    }

    /**
     * 资金流水
     */
    public function index()
    {
        $where = $this->getMore([
            ['time', ''],
            ['trading_type', 0],
            ['keywords', ''],
            ['ids', ''],
            ['export', 0],
        ]);
        $date = $this->service->getFlowList($where);

        return $this->success($date);
    }

    /**
     * 资金流水备注
     *
     * @param $id
     */
    public function setMark($id)
    {
        $data = $this->getMore([
            ['mark', ''],
        ]);
        $this->service->setMark($id, $data);

        return $this->success(100024);
    }

    /**
     * 账单记录
     */
    public function getFlowRecord()
    {
        $where = $this->getMore([
            ['type', 'day'],
            ['time', ''],
        ]);
        $data = $this->service->getFlowRecord($where);

        return $this->success($data);
    }
}
