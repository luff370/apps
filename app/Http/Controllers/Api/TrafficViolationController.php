<?php

namespace App\Http\Controllers\Api;

use App\Models\TrafficSign;
use Illuminate\Http\Request;
use App\Models\TrafficViolationContent;
use App\Services\System\AppVersionServices;
use App\Services\Cms\TrafficViolationApiService;

class TrafficViolationController extends Controller
{
    public function __construct(TrafficViolationApiService $service)
    {
        $this->service = $service;
    }

    /**
     * 违章曝光列表
     */
    public function list()
    {
        $filter = ['is_exposure' => 1];

        // 判断是否为审核状态，则显示审核数据
        $isAudit = AppVersionServices::getAuditStatusByVersion($this->getAppId(), $this->getMarketChannel(), $this->getAppVersion());
        if ($isAudit) {
            $filter = ['app_audit_data' => 1];
        }

        $data = $this->service->getAllByPage($filter, ['*'], ['show_time' => 'desc', 'id' => 'desc']);

        return $this->success($data);
    }

    /**
     * 违章曝光详情
     */
    public function details(Request $request)
    {
        $info = $this->service->details($request->get('id'));

        return $this->success($info);
    }

    /**
     * 违章举报上传
     */
    public function save(Request $request)
    {
        $data = $request->all();
        $data['app_id'] = $this->getAppId();
        $data['user_id'] = authUserId();
        $data['show_time'] = date('Y-m-d H:i:s');
        if (empty($data['violation_time'])) {
            $data['violation_time'] = date('Y-m-d H:i:s');
        }
        if (empty($data['car_type'])) {
            $data['car_type'] = '小型汽车';
        }
        if (empty($data['license_plate_number'])) {
            $data['license_plate_number'] = '00000';
        }

        $count = TrafficViolationContent::query()
            ->where('app_id', $data['app_id'])
            ->where('user_id', $data['user_id'])
            ->whereBetween('created_at', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->count();
        if ($count > 6) {
            return $this->fail('今天已经很努力了，明天再来吧');
        }

        $this->service->save($data);

        return $this->success();
    }

    /**
     * 用户违章举报记录
     */
    public function userRecords(Request $request)
    {
        $userId = authUserId();
        $data = $this->service->getUserRecords($userId, $request->get('audit_status'));

        return $this->success($data);
    }

    /**
     * 用户上传违章曝光详情
     */
    public function userDetails(Request $request)
    {
        $info = $this->service->details($request->get('id'), authUserId());

        return $this->success($info);
    }

    /**
     * 获得违章举报奖励
     *
     * @throws \App\Exceptions\ApiException
     * @throws \Throwable
     */
    public function getRewards(Request $request)
    {
        $this->service->getRewards($request->get('id'), authUserId());

        return $this->success(null, '领取成功');
    }

    public function signs(Request $request): \Illuminate\Http\JsonResponse
    {
        $cateId = $request->get('cate_id');
        $signs = TrafficSign::query()->when($cateId, function ($query) use ($cateId) {
            $query->where('cate_id', $cateId);
        })
            ->orderBy("sort", "asc")
            ->get(['title', 'url']);

        return $this->success($signs);
    }
}
