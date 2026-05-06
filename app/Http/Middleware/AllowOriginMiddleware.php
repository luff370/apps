<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 跨域中间件
 * Class AllowOriginMiddleware
 *
 * @package app\http\middleware
 */
class AllowOriginMiddleware
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin', '*');

        if ($request->getMethod() === 'OPTIONS') {
            return $this->withCorsHeaders(response('ok', 204), $origin, $request);
        }

        $response = $next($request);
        return $this->withCorsHeaders($response, $origin, $request);
    }

    private function withCorsHeaders($response, string $origin, Request $request)
    {
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        $allowHeaders = $requestHeaders ?: 'Origin, Content-Type, Cookie, X-CSRF-TOKEN, Accept, Authorization, X-XSRF-TOKEN, Form-type, Authori-zation';

        $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PATCH,PUT,DELETE,OPTIONS');
        $response->headers->set('Access-Control-Max-Age', '172800');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
