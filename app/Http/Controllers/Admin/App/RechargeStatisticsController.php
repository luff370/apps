<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistics\OperationStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeStatisticsController extends Controller
{
    /**
     * 充值统计汇总卡片数据。
     *
     * 前端充值统计页顶部漏斗指标调用这个接口，返回新增、活跃、下单、支付、试用、续费、取消等汇总。
     */
    public function summary(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->rechargeSummary($request->only([
            'app_id',
            'start_date',
            'end_date',
            'market_channel',
            'version',
        ])));
    }

    /**
     * 充值统计趋势图数据。
     *
     * 单日筛选按小时返回，多日筛选按天返回，前端根据 granularity 渲染横轴。
     */
    public function trend(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->rechargeTrend($request->only([
            'app_id',
            'start_date',
            'end_date',
            'metric',
            'market_channel',
            'version',
        ])));
    }
}
