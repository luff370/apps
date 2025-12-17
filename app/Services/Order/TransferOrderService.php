<?php

namespace App\Services\Order;

use App\Models\User;
use Yansongda\Pay\Pay;
use App\Services\Service;
use App\Models\TransferOrder;

class TransferOrderService extends Service
{
    public function __construct(TransferOrder $order)
    {
        $this->model = $order;
    }

    public function transferToUserByAlipayAccount($appId, $userId, $amount, $payeeAccount = '', $payeeName = '', $title = '', $operator = 'system')
    {
        if ($amount <= 0 || $amount > 1) {
            logger()->error("转账金额错误", ['amount' => $amount]);
            throw new \Exception('转账金额错误，请确认');
        }

        $payeeAccountType = "ALIPAY_LOGON_ID";
        if ($payeeAccount == '') {
            $alipayUserId = User::query()->where('id', $userId)->value('alipay_user_id');
            if (empty($alipayUserId)) {
                throw new \Exception('无有效的转账用户ID');
            }

            $payeeAccount = $alipayUserId;
            $payeeAccountType = "ALIPAY_USER_ID";
        }

        try {
            // 订单信息
            $orderData = [
                'payment_channel' => 'alipay',
                'product_code' => 'STD_RED_PACKET',
                'payee_account_type' => $payeeAccountType,
                'order_no' => generateOrderNo($userId),
                'app_id' => $appId,
                'user_id' => $userId,
                'amount' => $amount,
                'payee_account' => $payeeAccount,
                'payee_name' => $payeeName,
                'order_title' => $title,
                'operator' => $operator,
            ];
            $order = $this->model->newQuery()->create($orderData);

            // 转账信息
            $transferOrderData = [
                'product_code' => $order['product_code'],
                'biz_scene' => 'DIRECT_TRANSFER',
                'order_title' => $title,
                'out_biz_no' => $order['order_no'],
                'trans_amount' => $order['amount'],
                'payee_info' => [
                    'identity_type' => $order['payee_account_type'],
                    'identity' => $order['payee_account'],
                    'name' => $order['payee_name'],
                ],
                'business_params' => "{\"sub_biz_scene\":\"REDPACKET\"}",
            ];

            $res = Pay::alipay(config('pay'))->transfer($transferOrderData);
            if (empty($res['code'])) {
                $order->status = TransferOrder::StatusFailed;
                $order->save();
                throw new \Exception('网络错误请重试');
            }

            if ($res['code'] == '10000') {
                // 转账成功
                $order->status = TransferOrder::StatusSuccess;
                $order->trade_no = $res['order_id'];
                $order->settle_serial_no = $res['pay_fund_order_id'];
                $order->trans_date = $res['trans_date'];
                $order->save();
            } else {
                // 转账失败
                $order->status = TransferOrder::StatusFailed;
                $order->error_msg = $res['sub_msg'];
                $order->error_code = $res['sub_code'];
                $order->save();
            }

            return $order;
        } catch (\Exception $exception) {
            logger()->error('转账操作失败：' . $exception->getMessage());
            throw new \Exception($exception->getMessage());
        }
    }
}
