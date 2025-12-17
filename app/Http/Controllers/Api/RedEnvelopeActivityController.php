<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Activity\RedEnvelopeService;
use Illuminate\Http\Request;
use App\Models\UserAmountChangeLog;

class RedEnvelopeActivityController extends Controller
{
    public function __construct(RedEnvelopeService $redEnvelopeService)
    {
        $this->service = $redEnvelopeService;
    }

    // 领取红包
    public function getRedEnvelope(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->get('type');
        $userId = authUserId();

        $redEnvelopeInfo = $this->service->getRedEnvelope($type, $userId);

        return $this->success($redEnvelopeInfo);
    }


}
