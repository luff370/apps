<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Support\Services\UserBehaviorReportService;

class UserBehaviorController extends Controller
{
    public function report(Request $request, UserBehaviorReportService $service)
    {
        $report = $service->record($request);

        return $this->success(['report_id' => $report->id]);
    }
}
