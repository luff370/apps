<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Support\Traits\ServicesTrait;
use App\Support\Traits\CommonArgsTrait;
use App\Http\Controllers\Controller as BaseController;

class Controller extends BaseController
{
    use CommonArgsTrait, ServicesTrait;

    protected $service;

    /**
     * @param null $data
     * @param string $msg
     * @param int $status
     *
     */
    protected function success($data = null, $msg = "success", $status = 200): JsonResponse
    {
        if (is_string($data)) {
            $msg = $data;
            $data = null;
        }

        if (is_numeric($data)) {
            $msg = trans('api.' . $data);
            $data = null;
        }

        return response()->json(['status' => $status, 'msg' => $msg, 'data' => $data]);
    }

    protected function fail($msg, $data = null, $status = 400): JsonResponse
    {
        if (is_numeric($msg)) {
            $msg = trans('api.' . $msg);
        }

        return response()->json(['status' => $status, 'code' => $status, 'msg' => $msg, 'data' => $data]);
    }
}
