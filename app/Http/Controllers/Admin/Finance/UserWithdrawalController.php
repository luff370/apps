<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Support\Utils\UserNotice;
use App\Http\Controllers\Admin\Controller;
use App\Services\Finance\UserWithdrawalService;

/**
 * UserWithdrawalController
 */
class UserWithdrawalController extends Controller
{
    public function __construct(UserWithdrawalService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['user_id', ''],
            ['app_id', ''],
            ['account_type', ''],
            ['fund_source', ''],
            ['audit_user_id', ''],
            ['audit_status', ''],
            ['status', ''],
            ['product_id', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc'], ['user', 'audit']);

        return $this->success($data);
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
            ['reply_content', ''],
            ['remark', ''],
            ['audit_status', ''],
        ]);
        $this->service->update($id, $data);

        return $this->success(100001);
    }

    /**
     * 审核成功
     */
    public function adopt($id)
    {
        $info = $this->service->getRow($id);
        if ($info['audit_status'] > 0) {
            return $this->fail('已审核，不能重复操作');
        }

        $info->audit_status = 1;
        $info->save();

        UserNotice::sendAuditSuccessNotice($info->app_id, $info->user_id);

        return $this->success(100001);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        $isAudit = $this->service->isAudit($id);
        if ($isAudit) {
            return $this->fail('已审核数据不能删除');
        }

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
        $this->service->update($id, [$field => $value]);

        return $this->success(100014);
    }
}
