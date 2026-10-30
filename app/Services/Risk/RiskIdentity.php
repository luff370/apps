<?php

namespace App\Services\Risk;

use App\Models\User;
use App\Support\Services\ClientRequestContext;
use App\Support\Utils\Token;
use Illuminate\Http\Request;

class RiskIdentity
{
    public static function userId(Request $request): ?string
    {
        $token = ClientRequestContext::token($request);
        if (! $token) {
            return null;
        }
        $id = Token::cachedUserId($token);
        if (! $id || ! User::query()->where('id', $id)->where('app_id', (int) ClientRequestContext::appId($request))->where('is_del', 0)->exists()) {
            throw new \App\Exceptions\AuthException('登录失效，请重新登录');
        }

        return (string) $id;
    }
}
