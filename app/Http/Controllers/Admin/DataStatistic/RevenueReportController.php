<?php

namespace App\Http\Controllers\Admin\DataStatistic;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistics\OperationStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
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

    public function detail(string $date, int $appId, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->revenueDetail($date, $appId));
    }

    public function recollect(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->markRecollect($request->only(['date', 'app_id'])));
    }

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
