<?php

namespace App\Http\Controllers\Api;

use App\Services\Risk\ActivityEventService;
use Illuminate\Http\Request;

class RiskActivityController extends Controller
{
    public function batch(Request $request, ActivityEventService $service)
    {
        return $this->success($service->ingest($request));
    }
}
