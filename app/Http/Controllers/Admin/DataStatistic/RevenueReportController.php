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
     *
     * 该接口负责页面主表格、顶部汇总和平台汇总，服务层会把用户、充值、广告收益、广告访问日志合并。
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
     *
     * 详情弹窗复用列表统计口径，只缩小到某一天某个应用。
     */
    public function detail(string $date, int $appId, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->revenueDetail($date, $appId));
    }

    /**
     * 标记报表重新采集，供前端“重新采集”按钮触发补数。
     *
     * 当前接口先把广告收益日报状态置为采集中，真正的拉取平台数据可由后续采集任务消费该状态。
     */
    public function recollect(Request $request, OperationStatisticsService $service): JsonResponse
    {
        return $this->success($service->markRecollect($request->only(['date', 'app_id'])));
    }

    /**
     * 导出沿用列表口径，只扩大分页上限，由前端生成文件。
     *
     * 这样导出字段和列表字段保持一致，避免维护两套统计口径。
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
