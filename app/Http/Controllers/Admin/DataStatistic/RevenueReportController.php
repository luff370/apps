<?php

namespace App\Http\Controllers\Admin\DataStatistic;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistics\OperationStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    /**
     * 营收报表列表，按应用/日期维度返回汇总和平台拆分。
     */
    public function index(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->revenueReport($request->only([
            'app_keyword',
            'start_date',
            'end_date',
            'ad_platform',
            'data_status',
            'sort_field',
            'sort_order',
            'page',
            'limit',
        ])));
    }

    /**
     * 单日单应用营收详情。
     */
    public function detail(string $date, int $appId, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->revenueDetail($date, $appId));
    }

    /**
     * 标记报表重新采集，供前端“重新采集”按钮触发补数。
     */
    public function recollect(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->markRecollect($request->only(['date', 'app_id'])));
    }

    /**
     * 导出沿用列表口径，只扩大分页上限，由前端生成文件。
     */
    public function export(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->revenueReport(array_merge($request->only([
            'app_keyword',
            'start_date',
            'end_date',
            'ad_platform',
            'data_status',
            'sort_field',
            'sort_order',
        ]), [
            'page' => 1,
            'limit' => 10000,
        ])));
    }
}
