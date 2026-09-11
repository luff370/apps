<?php

namespace App\Http\Controllers\Admin\Statistic;

use App\Http\Controllers\Admin\Controller;
use App\Services\Statistics\UserStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserStatisticController extends Controller
{
    /**
     * 用户统计汇总：活跃用户、新增用户（新增 UUID）、注册用户、注册率。
     */
    public function getBasic(Request $request, UserStatisticsService $service): JsonResponse
    {
        return $this->success($service->reportBasic($this->reportFilter($request)));
    }

    /**
     * 用户统计趋势：按天返回新增用户、注册用户、注册率。
     */
    public function getTrend(Request $request, UserStatisticsService $service): JsonResponse
    {
        return $this->success($service->reportTrend($this->reportFilter($request)));
    }

    /**
     * 用户统计按应用列表：新增用户、活跃用户、注册用户、注册率。
     */
    public function getAppList(Request $request, UserStatisticsService $service): JsonResponse
    {
        return $this->success($service->reportByApp($this->reportFilter($request)));
    }

    private function reportFilter(Request $request): array
    {
        return $request->only([
            'app_id',
            'market_channel',
            'data',
            'start_date',
            'end_date',
        ]);
    }

    public function getWechat(): JsonResponse
    {
        return $this->success([]);
    }

    public function getWechatTrend(): JsonResponse
    {
        return $this->success(['xAxis' => [], 'series' => []]);
    }

    public function getRegion(): JsonResponse
    {
        return $this->success([]);
    }

    public function getSex(): JsonResponse
    {
        return $this->success([]);
    }

    public function getExcel(): JsonResponse
    {
        return $this->success([]);
    }
}
