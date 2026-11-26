<?php

namespace App\Http\Controllers\Admin\Statistic;

use App\Exceptions\AuthException;
use App\Http\Controllers\Admin\Controller;
use App\Models\SystemMenu;
use App\Services\Statistics\UserStatisticsService;
use App\Services\System\Admin\SystemRoleServices;
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

    public function getChannelList(Request $request, UserStatisticsService $service): JsonResponse
    {
        $request->validate(['app_id' => 'required|integer|min:1']);

        return $this->success($service->reportByChannel($this->reportFilter($request)));
    }

    public function getDeviceList(Request $request, UserStatisticsService $service, SystemRoleServices $roles): JsonResponse
    {
        $admin = $request->adminInfo();
        if ($admin['level'] && !SystemMenu::query()
            ->where('unique_auth', 'data-statistic-user-statistics')
            ->where('is_del', 0)
            ->whereIn('id', $roles->getRoleIds($admin['roles']))
            ->exists()) {
            throw new AuthException(110000);
        }

        $filters = $request->validate([
            'app_id' => 'nullable|integer|min:0',
            'market_channel' => 'nullable|string|max:64',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'keyword' => 'nullable|string|max:128',
            'registered' => 'nullable|in:0,1',
            'source' => 'nullable|in:default,umeng',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->success($service->reportDevices($filters));
    }

    private function reportFilter(Request $request): array
    {
        return $request->only([
            'app_id',
            'market_channel',
            'data',
            'start_date',
            'end_date',
            'source',
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
