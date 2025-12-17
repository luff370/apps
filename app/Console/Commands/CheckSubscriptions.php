<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User;
use App\Models\MemberOrder;
use App\Support\Utils\Apple;
use Illuminate\Console\Command;
use App\Models\SubscriptionOrder;

class CheckSubscriptions extends Command
{
    // 命令的名称和签名
    protected $signature = 'subscriptions:check';

    // 命令的描述
    protected $description = 'Check and update user subscriptions based on Apple receipt validation';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // 获取所有有订阅的用户
        $orders = SubscriptionOrder::query()->whereNotNull('latest_receipt')->orderBy('id', 'desc')->get();

        foreach ($orders as $order) {
            // 调用验证收据的方法
            $this->checkSubscriptionStatus($order);
        }

        $this->info('All subscriptions checked successfully.');
    }

    // 验证收据并更新订阅状态
    private function checkSubscriptionStatus(SubscriptionOrder $subscription): void
    {
        $receipt = $subscription->latest_receipt;
        $response = Apple::validateReceipt($receipt, true);

        $data = $response->json();

        // 检查返回的状态码
        if ($data['status'] == 0) {
            // 验证成功，获取最新地订阅信息
            $latestReceiptInfo = end($data['latest_receipt_info']);
            $pendingRenewal = end($data['pending_renewal_info']);
            $originalTransactionId = $latestReceiptInfo['original_transaction_id'];

            // 自动续订状态
            $autoRenewStatus = $pendingRenewal['auto_renew_status'] ?? 0;
            // 订阅过期状态
            $expirationIntent = $pendingRenewal['expiration_intent'] ?? 0;
            $expirationReason = Apple::getExpirationReason($expirationIntent);
            // 获取订阅的过期时间
            $expirationDateMs = $latestReceiptInfo['expires_date_ms'];
            $expirationDate = Carbon::createFromTimestampMs($expirationDateMs);
            // 取消时间
            $cancellationDate = $pendingRenewal['cancellation_date'] ?? null;
            // 试用状态
            $isTrialPeriod = $latestReceiptInfo['is_trial_period'] ?? false;
            // 订阅失败重试状态
            $isInBillingRetryPeriod = $pendingRenewal['is_in_billing_retry_period'] ?? false;
            // 订阅状态
            $subscriptionStatus = Apple::determineSubscriptionStatus(
                $expirationDateMs,
                $cancellationDate,
                $isTrialPeriod,
                $isInBillingRetryPeriod,
                $pendingRenewal
            );
            // 支付状态
            $paymentStatus = Apple::determinePaymentStatus($expirationDate->unix(), $isTrialPeriod, $isInBillingRetryPeriod, $expirationIntent);
            // 会员状态
            $membershipStatus = Apple::determineMembershipStatus($expirationDate->unix(), $cancellationDate, $isTrialPeriod);
            // 时间支付金额
            $payAmount = Apple::calculateTotalAmountPaid($data['latest_receipt_info'], $originalTransactionId);

            // 更新用户的订阅信息
            $memberOrder = MemberOrder::query()->where('trade_no', $originalTransactionId)->first();
            $memberOrder->expires_date = $expirationDate;
            $memberOrder->pay_status = $paymentStatus;
            $memberOrder->member_status = $membershipStatus;
            $memberOrder->pay_price = $payAmount;
            $memberOrder->vip_day = $expirationDate->diffInDays(now());
            $memberOrder->save();

            // 删除重复数据
            // MemberOrder::query()->where('trade_no', $originalTransactionId)
            //     ->where('id', '<>', $memberOrder->id)
            //     ->delete();

            // 更新用户的会员状态
            $user = User::query()->where('id', $memberOrder->user_id)->first();
            if ($user) {
                $user->is_vip = in_array($membershipStatus, ['trial', 'active']);
                $user->vip_type = $isTrialPeriod == 'true' ? 3 : 1;
                $user->overdue_time = $expirationDate->unix();
                $user->total_charge = $payAmount;
                $user->expires_date = $expirationDate;

                $user->save();
            }

            $subscription->auto_renew_status = $autoRenewStatus;
            $subscription->is_trial_period = $isTrialPeriod == 'true';
            $subscription->status = $subscriptionStatus;
            $subscription->purchase_date = Carbon::parse($latestReceiptInfo['original_purchase_date']);
            $subscription->expires_date = $expirationDate;
            $subscription->renewal_date = $expirationDate;
            $subscription->cancellation_date = $cancellationDate;
            $subscription->subscribe_fail_reason = $expirationReason;
            $subscription->latest_receipt = $data['latest_receipt'];
            $subscription->pay_amount = $payAmount;
            $subscription->subscribe_success_count = Apple::getSubscriptionSuccessCount($data['latest_receipt_info'], $originalTransactionId);
            $subscription->subscribe_fail_count = Apple::getSubscriptionFailureCount($data['pending_renewal_info'], $originalTransactionId);
            $subscription->save();

            $this->info("Subscription updated for user {$subscription->id}");
        } else {
            // 如果收据验证失败，可以记录日志或发送通知
            $this->error("Failed to validate receipt for user {$subscription->id}, status: {$data['status']}");
        }
    }
}
