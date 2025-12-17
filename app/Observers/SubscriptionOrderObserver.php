<?php

namespace App\Observers;

use App\Support\Utils\Apple;
use Carbon\Carbon;
use App\Models\User;
use App\Models\MemberOrder;
use App\Models\SubscriptionOrder;

class SubscriptionOrderObserver
{
    /**
     * Handle the SubscriptionOrder "created" event.
     */
    public function created(SubscriptionOrder $subscriptionOrder): void
    {
        //
    }

    /**
     * Handle the SubscriptionOrder "updated" event.
     */
    public function updated(SubscriptionOrder $subscriptionOrder): void
    {
        // 订阅信息更新，更改用户会员状态
        $updateData = $subscriptionOrder->getDirty();
        logger()->info('---订阅数据变更---', ['updateData' => $updateData, 'subscriptionOrder' => $subscriptionOrder]);

        if (isset($updateData['expires_date']) || isset($updateData['status']) || isset($updateData['is_trial_period'])) {
            $memberOrder = MemberOrder::query()->where('trade_no', $subscriptionOrder->original_transaction_id)->first();
            $user = User::query()->where('id', $subscriptionOrder->user_id)->first();
            if (!$user || !$memberOrder) {
                logger()->error('订阅状态变更，会员信息不存在', ['memberOrder' => $memberOrder, 'user' => $user]);

                return;
            }

            // 支付状态
            // $paymentStatus = Apple::determinePaymentStatus($expirationDateMs, $isTrialPeriod, $isInBillingRetryPeriod, $expirationIntent);
            // 会员状态
            // $membershipStatus = Apple::determineMembershipStatus($expirationDateMs, $cancellationDate, $isTrialPeriod);

            // 用户会员信息更新
            $user->overdue_time = Carbon::parse($updateData['expires_date'])->unix();
            $user->expires_date = $updateData['expires_date'];
            $user->vip_type = $updateData['is_trial_period'] ? 3 : 1;
            $user->is_vip = in_array($updateData['status'], ['active', 'trial']);
            $user->save();

            // 会员订单信息更新
            $memberOrder->expires_date = $updateData['expires_date'];
            // $memberOrder->is_trial_period = $updateData['is_trial_period'];
            $memberOrder->save();
        }
    }

    /**
     * Handle the SubscriptionOrder "deleted" event.
     */
    public function deleted(SubscriptionOrder $subscriptionOrder): void
    {
        //
    }

    /**
     * Handle the SubscriptionOrder "restored" event.
     */
    public function restored(SubscriptionOrder $subscriptionOrder): void
    {
        //
    }

    /**
     * Handle the SubscriptionOrder "force deleted" event.
     */
    public function forceDeleted(SubscriptionOrder $subscriptionOrder): void
    {
        //
    }
}
