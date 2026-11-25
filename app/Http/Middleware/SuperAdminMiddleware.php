<?php

namespace App\Http\Middleware;

use App\Exceptions\AuthException;
use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $level = $request->adminInfo()['level'] ?? null;
        if (!in_array($level, [0, '0'], true)) {
            throw new AuthException(110000);
        }

        return $next($request);
    }
}
