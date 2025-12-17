<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/**
 * Class ApiException
 */
class ApiException extends Exception
{
    public function __construct($message, $code = 400, \Throwable $previous = null)
    {
        if (is_array($message)) {
            $errInfo = $message;
            $message = $errInfo[1] ?? '未知错误';
        }

        if (is_numeric($message)) {
            $message = trans('api.' . $message);
        }

        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => $this->code, 'msg' => $this->message]);
    }
}
