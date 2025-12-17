<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserFeedbackService;
use App\Support\Utils\UserNotice;

/**
 * UserFeedbackController
 */
class UserFeedbackController extends Controller
{
    public function __construct(UserFeedbackService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $filter = $this->getMore([
            ['user_id', ''],
            ['app_id', ''],
            ['market_channel', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc'], ['user']);

        return $this->success($data);
    }


    /**
     * 编辑
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['recover_content', '']
        ]);
        $data['status'] = 1;
        $data['admin_name'] = adminInfo()['real_name'] ?? '未知';

        $this->service->update($id, $data);

        $info = $this->service->getRow($id);
        if (!empty($info)) {
            UserNotice::send($info['app_id'], $info['user_id'], \App\Models\UserNotice::TypeFeedbackReply, "留言回复", $info['recover_content']);
        }

        return $this->success('回复成功');
    }


    /**
     * 删除数据
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field): \Illuminate\Http\JsonResponse
    {
        if (!$id = intval($id)) {
            return $this->fail(100100);
        }
        $this->service->update($id, [$field => $value]);

        return $this->success(100014);
    }
}
