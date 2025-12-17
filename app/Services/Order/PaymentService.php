<?php

namespace App\Services\Order;

use Carbon\Carbon;
use App\Models\User;
use App\Services\Service;
use App\Models\MemberOrder;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

class PaymentService extends Service
{
    /**
     * @throws ApiException
     */
    public function paySuccessful($orderNo, $tradeNo, $amount, $payment): void
    {
        Log::info("支付成功，更改订单状态", ['orderNo' => $orderNo, 'tradeNo' => $tradeNo, 'amount' => $amount]);

        $order = MemberOrder::query()->where('order_no', $orderNo)->first();
        if (!$order) {
            throw new ApiException('订单获取失败');
        }

        try {
            \DB::beginTransaction();
            if ($order['pay_status'] != MemberOrder::PAY_STATUS_PAID) {
                $member = User::query()->where('id', $order->user_id)->first();
                if (!$member) {
                    throw new ApiException('用户信息获取失败');
                }

                $order->trade_no = $tradeNo;
                $order->pay_status = MemberOrder::PAY_STATUS_PAID;
                $order->member_status = 'active';
                $order->pay_price = $amount;
                $order->mch_id = $payment['mch_id'];
                $order->pay_type = $payment['pay_channel'];
                $order->pay_source = $payment['pay_type'];
                $order->pay_time = time();
                $order->save();

                $overdueTime = today()->endOfDay();
                if ($member['is_vip'] == 1) {
                    $overdueTime = Carbon::parse($member['overdue_time']);
                }

                switch ($order['member_type']) {
                    case 'year':
                        $overdueTime = $overdueTime->addYears($order['quantity']);
                        break;
                    case 'quarter':
                        $overdueTime = $overdueTime->addMonths($order['quantity'] * 3);
                        break;
                    case 'month':
                        $overdueTime = $overdueTime->addMonths($order['quantity']);
                        break;
                    case 'week':
                        $overdueTime = $overdueTime->addWeeks($order['quantity']);
                        break;
                    case 'day':
                        $overdueTime = $overdueTime->addDays($order['quantity']);
                        break;
                }
                $member->is_vip = 1;
                $member->vip_type = 1;
                $member->overdue_time = $overdueTime->unix();
                $member->expires_date = $overdueTime->toDateTimeString();
                $member->save();

                \DB::commit();
                Log::info("会员订单支付成功回调处理成功：" . $orderNo, $member->toArray());
            }
        } catch (\Exception $exception) {
            \DB::rollBack();
            Log::error('会员订单支付成功回调处理失败：' . $exception->getMessage());
            throw new ApiException($exception->getMessage());
        }
    }
}
