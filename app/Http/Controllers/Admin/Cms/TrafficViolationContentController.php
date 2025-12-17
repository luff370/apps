<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Models\TrafficViolationContent;
use App\Http\Controllers\Admin\Controller;
use App\Services\Cms\TrafficViolationContentService;

/**
 * TrafficViolationContentController
 */
class TrafficViolationContentController extends Controller
{
    public function __construct(TrafficViolationContentService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['user_id', ''],
            ['type', ''],
            ['car_type', ''],
            ['audit_status', ''],
            ['audit_user_id', ''],
            ['status', ''],
            ['notification_status', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc'], ['user']);

        return $this->success($data);
    }

    /**
     * 新增表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function create()
    {
        return $this->success($this->service->createForm());
    }

    /**
     * 保存新建
     */
    public function store()
    {
        $data = $this->getMore([
            ['app_id', ''],
            ['user_id', ''],
            ['type', ''],
            ['car_type', ''],
            ['images', []],
            ['city', ''],
            ['address', ''],
            ['description', ''],
            ['province_code', ''],
            ['license_plate_number', ''],
            ['violation_time', ''],
            ['is_exposure', '0'],
            ['audit_status', '0'],
            ['audit_user_id', ''],
            ['reply_content', ''],
            ['reward_count', '0'],
            ['status', '1'],
        ]);
        $data['show_time'] = $data['violation_time'];

        $this->service->save($data);

        return $this->success(100021);
    }

    /**
     * 编辑表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm($id));
    }

    /**
     * 数据更新
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['app_id', ''],
            ['type', ''],
            ['car_type', []],
            ['images', ''],
            ['city', ''],
            ['address', ''],
            ['description', ''],
            ['province_code', ''],
            ['license_plate_number', ''],
            ['violation_time', ''],
            ['is_exposure', '0'],
            ['audit_status', '0'],
            ['audit_user_id', ''],
            ['reply_content'],
            ['reward_count', '0'],
            ['status', '1'],
        ]);

        $this->service->update($id, $data);

        return $this->success(100001);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field)
    {
        if (!$id = intval($id)) {
            return $this->fail(100100);
        }
        TrafficViolationContent::query()->where('id', $id)->update([$field => $value]);

        return $this->success(100014);
    }
}
