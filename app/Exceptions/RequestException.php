<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class RequestException extends Exception
{
    public function __construct(string $message = "", int $code = 400)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => $this->code, 'msg' => $this->message]);
    }
}
