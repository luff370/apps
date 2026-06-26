<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistics\OperationStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeStatisticsController extends Controller
{
    public function summary(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->rechargeSummary($request->only(['app_id', 'start_date', 'end_date'])));
    }

    public function trend(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->rechargeTrend($request->only(['app_id', 'start_date', 'end_date', 'metric'])));
    }
}
