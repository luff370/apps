<?php

namespace App\Observers;

use App\Models\MemberOrder;
use App\Models\User;
use Carbon\Carbon;

class MemberOrderObserver
{
    /**
     * Handle the MemberOrder "created" event.
     */
    public function created(MemberOrder $memberOrder): void
    {
        //
    }

    /**
     * Handle the MemberOrder "updated" event.
     */
    public function updated(MemberOrder $memberOrder): void
    {
        // 会员订单信息更新，更改用户会员状态
        $updateData = $memberOrder->getDirty();
        logger()->info('---memberOrder-Updated---', ['updateData' => $updateData, 'memberOrder' => $memberOrder]);

        if (isset($updateData['expires_date']) || isset($updateData['subscribe_status']) || isset($updateData['is_trial_period'])) {
            $user = User::query()->where('id', $memberOrder->user_id)->first();
            if (!$user) {
                return;
            }

            if (isset($updateData['expires_date'])) {
                $user->overdue_time = Carbon::parse($updateData['expires_date'])->unix();
            }
            if (isset($updateData['subscribe_status'])) {
                $user->is_vip = in_array($updateData['subscribe_status'], ['active', 'trial']);
            }
            if (!empty($updateData['is_trial_period'])) {
                $user->vip_type = $updateData['is_trial_period'] ? 3 : 1;
            }

            if ($user->is_vip && $user->vip_type == 0) {
                $user->vip_type = 1;
            }

            $user->save();
        }
    }

    /**
     * Handle the MemberOrder "deleted" event.
     */
    public function deleted(MemberOrder $memberOrder): void
    {
        //
    }

    /**
     * Handle the MemberOrder "restored" event.
     */
    public function restored(MemberOrder $memberOrder): void
    {
        //
    }

    /**
     * Handle the MemberOrder "force deleted" event.
     */
    public function forceDeleted(MemberOrder $memberOrder): void
    {
        //
    }
}
