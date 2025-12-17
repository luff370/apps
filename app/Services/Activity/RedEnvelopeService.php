<?php

namespace App\Services\Activity;

use App\Models\User;
use App\Models\UserAmountChangeLog;
use App\Services\Service;
use Illuminate\Http\Request;

class RedEnvelopeService extends Service
{
    public function getRedEnvelope($type, $userId): array
    {
        $userBalance = User::query()->where('id', $userId)->value('balance');

        $redEnvelopeAmount = 0.01;
        if ($userBalance < 90) {
            switch ($type) {
                case 'favorable_comment':
                    $redEnvelopeAmount = 8.8;
                    break;
                case 'incentive_ad_give_red_envelope':
                    $redEnvelopeAmount = rand(0, 9) + (rand(1, 99) / 100);;
                    break;
                case 'incentive_ad_for_direct_withdraw':
                    $redEnvelopeAmount = 0.01;
                    break;
            }
        }

        User::query()->where('id', $userId)->update([
            'balance' => $userBalance + $redEnvelopeAmount
        ]);
        UserAmountChangeLog::query()->create([
            'app_id' => $this->getAppId(),
            'user_id' => $userId,
            'amount' => $redEnvelopeAmount,
            'before_amount' => $userBalance,
            'after_amount' => $userBalance + $redEnvelopeAmount,
            'type' => UserAmountChangeLog::TYPE_RECEIVE_RED_ENVELOPE,
            'remark' => '领取红包'
        ]);

        return ['red_envelope_amount' => $redEnvelopeAmount];
    }

}
