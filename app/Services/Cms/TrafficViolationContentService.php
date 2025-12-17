<?php

namespace App\Services\Cms;

use App\Models\SystemApp;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Dao\Cms\TrafficViolationContentDao;
use App\Support\Services\FormBuilder as Form;

/**
 * Class TrafficViolationContentService
 */
class TrafficViolationContentService extends Service
{
    /**
     * TrafficViolationContentService constructor.
     */
    public function __construct(TrafficViolationContentDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $appsMap = SystemApp::idToNameMap();
        $userIds = array_column($list->toArray(), 'user_id');
        $userCount = $this->dao->newQuery()
            ->selectRaw("count(id) as count, user_id")
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['user_id'] => $item['count']];
            })->toArray();

        $auditSuccessCount = $this->dao->newQuery()
            ->selectRaw("count(id) as count, user_id")
            ->whereIn('user_id', $userIds)
            ->where('audit_status', '=', 1)
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['user_id'] => $item['count']];
            })->toArray();

        foreach ($list as &$item) {
            $item['app_name'] = $appsMap[$item['app_id']] ?? '';
            $item['user_name'] = $item['user']['nickname'] ?? '--';
            $item['user_commit_count'] = $userCount[$item['user_id']] ?? 0;
            $item['audit_success_count'] = $auditSuccessCount[$item['user_id']] ?? 0;
            $item['audit_status_name'] = trans('traffic_violation_content.audit_status_map')[$item['audit_status']] ?? '';
            $item['status_name'] = trans('traffic_violation_content.status_map')[$item['status']] ?? '';
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm()
    {
        return create_form('添加', $this->createUpdateForm(), url('/admin/cms/traffic_violation_content'));
    }

    /**
     * 编辑表单获取
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/cms/traffic_violation_content/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $f[] = Form::select('app_id', '应用', $info['app_id'] ?? 10002)->options($this->toFormSelect(SystemApp::idToNameMap()))->filterable(true)->requiredNum();
        if ($info) {
            $f[] = Form::input('user_id', '用户', $info['user_id'] ?? '')->readonly(true);
        }
        $f[] = Form::select('type', '举报类型', $info['type'] ?? '')->options($this->toFormSelect(trans('traffic_violation_content.type_map'), true))->filterable(true)->required();
        $f[] = Form::select('car_type', '车辆类型', $info['car_type'] ?? '')->options($this->toFormSelect(trans('traffic_violation_content.car_type_map'), true))->filterable(true)->required();
        $f[] = Form::uploadImages('images', '违法照片', config('admin.url') . '/admin/file/upload', $info['images'] ?? [])->headers(['Authori-Zation' => request()->header(config('cookie.token_name', 'Authori-zation'))]);
        $f[] = Form::text('city', '违法城市', $info['city'] ?? '');
        $f[] = Form::textarea('address', '违法地点', $info['address'] ?? '');
        $f[] = Form::textarea('description', '违法描述', $info['description'] ?? '');
        $f[] = Form::text('province_code', '省份简称', $info['province_code'] ?? '');
        $f[] = Form::text('license_plate_number', '车牌号码', $info['license_plate_number'] ?? '');
        $f[] = Form::dateTime('violation_time', '违法时间', $info['violation_time'] ?? date('Y-m-d H:i:s'));
        $f[] = Form::radio('is_exposure', '是否公开', $info['is_exposure'] ?? 0)->options($this->toFormSelect(['1' => '是', '0' => '否']));
        $f[] = Form::radio('audit_status', '审核状态', $info['audit_status'] ?? 0)->options($this->toFormSelect(trans('traffic_violation_content.audit_status_map')));
        $f[] = Form::textarea('reply_content', '审核回复', $info['reply_content'] ?? '');
        $f[] = Form::number('reward_count', '奖励数量', config('traffic.reward_count'))->readonly(true);
        $f[] = Form::radio('status', '状态', $info['status'] ?? 1)->options($this->toFormSelect(trans('traffic_violation_content.status_map')));

        return $f;
    }

    public function update($id, $data)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('数据不存在');
        }

        if ($info['audit_status'] != 0 && $info['audit_status'] != $data['audit_status']) {
            throw new AdminException('不能再次修改审核状态');
        }

        if ($info['audit_status'] == 0 && $data['audit_status'] > 0) {
            $data['audit_time'] = now()->toDateTimeString();
            $data['audit_user_id'] = adminId() ?? 0;
            $data['notification_status'] = 1;
            $data['is_top'] = 1;
            // 审核通过
            if ($data['audit_status'] == 1) {
                if (empty($data['reply_content'])) {
                    $data['reply_content'] = '正义从不缺席，请领取您的正能量';
                }
            }

            // 审核不通过
            if ($data['audit_status'] == 2) {
                if (empty($data['reply_content'])) {
                    $data['reply_content'] = '您好，您的举报已收到，行为已超时或已被提交，未能通过审核。感谢您的支持！';
                }
            }
        }

        return $this->dao->update($id, $data);
    }
}
