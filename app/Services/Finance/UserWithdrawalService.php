<?php

namespace App\Services\Finance;

use App\Models\SystemApp;
use App\Services\Service;
use App\Models\TransferOrder;
use App\Models\UserWithdrawal;
use App\Support\Utils\UserNotice;
use App\Exceptions\AdminException;
use App\Support\Services\FormOptions;
use App\Dao\Finance\UserWithdrawalDao;
use App\Support\Services\FormBuilder as Form;

/**
 * Class UserWithdrawalService
 */
class UserWithdrawalService extends Service
{
    /**
     * UserWithdrawalService constructor.
     */
    public function __construct(UserWithdrawalDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $apps = SystemApp::idToNameMap();
        $transferStatusMap = TransferOrder::statusMap();
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
            $item['account_type_name'] = trans('user_withdrawal.account_type_map')[$item['account_type']] ?? '';
            $item['fund_source'] = trans('user_withdrawal.fund_source_map')[$item['fund_source']] ?? '';
            $item['audit_user_name'] = $item['audit']['real_name'] ?? '';
            $item['audit_status_name'] = trans('user_withdrawal.audit_status_map')[$item['audit_status']] ?? '';
            $item['audit_success_count'] = $this->dao->getAuditSuccessCount($item['user_id'], $item['app_id']);
            $item['transfer_status_name'] = $transferStatusMap[$item['transfer_status']] ?? '';
        }

        return $list;
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

        return create_form('修改', $this->createUpdateForm($info), url('/admin/finance/user_withdrawal/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm($info = []): array
    {
        $f[] = Form::select('app_id', '应用', $info['app_id'] ?? '')->options(FormOptions::systemApps())->disabled(true);
        $f[] = Form::input('user_name', '用户账号', $info['user']['account'] ?? '')->readonly(true);
        $f[] = Form::input('user_id', '用户ID', $info['user_id'] ?? '')->readonly(true);
        $f[] = Form::input('product_id', '产品ID', $info['product_id'] ?? '')->readonly(true);
        $f[] = Form::number('amount', '提现金额', $info['amount'] ?? '')->readonly(true);
        $f[] = Form::number('use_integral', '使用积分', $info['use_integral'] ?? '0')->readonly(true);
        $f[] = Form::number('use_balance', '使用余额', $info['use_balance'] ?? '0.00')->readonly(true);
        $f[] = Form::text('fund_source', '资金来源', trans('user_withdrawal.fund_source_map')[$info['fund_source']] ?? '')->readonly(true);
        $f[] = Form::select('account_type', '账号类型', $info['account_type'] ?? '')->options($this->toFormSelect(trans('user_withdrawal.account_type_map')))->disabled(true);
        $f[] = Form::text('account_name', '账户名', $info['account_name'] ?? '')->readonly(true);
        $f[] = Form::text('account', '账号', $info['account'] ?? '')->readonly(true);
        if ($info['audit_status'] > 0) {
            $f[] = Form::text('audit_status_name', '处理结果', trans('user_withdrawal.audit_status_map')[$info['audit_status']] ?? '')->readonly(true);
            $f[] = Form::text('audit_user_name', '审核人', $info['audit']['real_name'] ?? '')->readonly(true);
            $f[] = Form::text('audit_time', '审核时间', $info['audit_time'] ?? '')->readonly(true);
        } else {
            $f[] = Form::radio('audit_status', '处理结果', $info['audit_status'] ?? '0')->options($this->toFormSelect(trans('user_withdrawal.audit_status_map')));
        }
        $f[] = Form::textarea('reply_content', '回复', $info['reply_content'] ?? '');
        $f[] = Form::textarea('remark', '备注', $info['remark'] ?? '');

        return $f;
    }

    /**
     * @throws \App\Exceptions\AdminException
     */
    public function update($id, $data)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException("数据获取失败");
        }

        try {
            \DB::beginTransaction();
            if ($info['audit_status'] > 0) {
                // 已审核 不允许修改审核状态
                unset($data['audit_status']);
            } elseif ($data['audit_status'] > 0) {
                // 未审核 记录审核信息
                $data['audit_time'] = now()->toDateTimeString();
                $data['audit_user_id'] = adminId();

                if ($data['audit_status'] == UserWithdrawal::AUDIT_STATUS_FAIL) {
                    // 审核失败，退回积分和余额
                    $this->afterAuditFailed($info);
                } else {
                    // 审核成功
                    UserNotice::sendWithdrawalSuccessNotice($info['app_id'], $info['user_id']);
                }
            }

            $res = $this->dao->update($id, $data);
            \DB::commit();

            return $res;
        } catch (\Throwable $e) {
            \DB::rollBack();
            logger()->error($e->getMessage());
            throw new AdminException('操作失败，请重试');
        }
    }

    public function afterAuditFailed($info)
    {
        $userInfo = $this->userService()->get($info['user_id']);
        $userInfo['integral'] += $info['use_integral'];
        $userInfo['balance'] += $info['use_balance'];
        $userInfo->save();

        if ($info['use_integral'] > 0) {
            // 增加用户积分退回记录
            $this->userBillService()->income('withdrawal_fail_refund_energy', $info['app_id'], $info['user_id'], (int) $info['use_integral'], $userInfo['integral'], $info['id']);
        }
        if ($info['use_balance'] > 0) {
            // 增加用户余额退回记录
            $this->userBillService()->income('withdrawal_fail_refund_balance', $info['app_id'], $info['user_id'], (int) $info['use_balance'], $userInfo['balance'], $info['id']);
        }

        UserNotice::sendWithdrawalFailNotice($info['app_id'], $info['user_id']);
    }

    public function isAudit($id): bool
    {
        return (bool) $this->dao->newQuery()->where('id', $id)->where('audit_status', '>', 0)->count();
    }

    public function autoTransfer(UserWithdrawal $info): void
    {
        // 临时设置成0.02（国庆双倍）
        // $info['amount'] = 0.02;
        $maxAmount = config('traffic.auto_transfer_amount');
        $paymentChannels = ['alipay'];
        try {
            // 判断用户提现次数
            // $withdrawalCount = $this->dao->newQuery()->where('user_id', $info['user_id'])->where('audit_status', '=', 1)->count();
            // if ($withdrawalCount > 10) {
            //     throw new \Exception("该账号已提现过{$withdrawalCount}次，请手动操作确认");
            // }

            if ($info['amount'] > $maxAmount) {
                throw new \Exception("自动提现金额超过{$maxAmount}元，请手动确认操作");
            }

            if (!in_array($info['account_type'], $paymentChannels)) {
                throw new \Exception("未开通{$info['account_type']}的自动提现，请手动确认操作");
            }

            $order = $this->transferOrderService()->transferToUserByAlipayAccount($info['app_id'], $info['user_id'], $info['amount'], $info['account_name'], $info['account'], '奖励提现');
            if ($order['status'] == TransferOrder::StatusSuccess) {
                // 转账成功
                $info['transfer_order_no'] = $order['order_no'];
                $info['audit_status'] = UserWithdrawal::AUDIT_STATUS_SUCCESS;
                $info['transfer_status'] = TransferOrder::StatusSuccess;
                $info['audit_time'] = now()->toDateTimeString();
                $info->save();

                UserNotice::sendWithdrawalSuccessNotice($info['app_id'], $info['user_id']);
            } else {
                // 转账失败
                $info['audit_status'] = UserWithdrawal::AUDIT_STATUS_FAIL;
                $info['audit_time'] = now()->toDateTimeString();
                $info['transfer_status'] = TransferOrder::StatusFailed;
                $info['transfer_error_msg'] = $order['error_msg'];
                $info['reply_content'] = $order['error_msg'];
                $info->save();
                $this->afterAuditFailed($info);
            }
        } catch (\Exception $e) {
            logger()->error('用户提现自动转账错误：' . $e->getMessage(), $info->toArray());
            // 转账异常
            $info['transfer_status'] = TransferOrder::StatusFailed;
            $info['transfer_error_msg'] = $e->getMessage();
            $info['reply_content'] = $e->getMessage();
            $info->save();
        }
    }
}
