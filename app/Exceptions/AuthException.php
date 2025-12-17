<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/**
 * Class AuthException
 */
class AuthException extends Exception
{
    public function __construct($message, $code = 401, \Throwable $previous = null)
    {
        if (is_array($message)) {
            $errInfo = $message;
            $message = $errInfo[1] ?? '未知错误';
        }

        if (is_numeric($message)) {
            $code = $message;
            $message = trans('admin.' . $message);
        }

        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => $this->code, 'code' => $this->code, 'msg' => $this->message]);
    }
}
