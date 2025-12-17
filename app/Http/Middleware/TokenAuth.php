<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Support\Utils\Token;
use App\Support\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

class TokenAuth
{
    use ApiResponse;

    /**
     * @throws \App\Exceptions\AuthException
     */
    public function handle(Request $request, \Closure $next)
    {
        $token = $request->header('Token');
        logger()->info('-----token----' . $token, $request->all());
        if (empty($token)) {
            return $this->failed('登录失效,无有效token参数', FoundationResponse::HTTP_UNAUTHORIZED);
        }

        $tokenData = Token::verify($token);
        $userId = $tokenData['user_id'];

        Request::macro('authUserId', function () use ($userId) {
            return $userId;
        });

        return $next($request);
    }
}
