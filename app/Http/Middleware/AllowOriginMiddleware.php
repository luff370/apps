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
        $response = $next($request);
        $origin = request()->server('HTTP_ORIGIN') ?? '*';
        // $allow_origin = [
        //     'http://localhost:8000',
        // ];
        // if (in_array($origin, $allow_origin)) {}

        if (request()->getMethod() == 'OPTIONS') {
            $response = response('ok');
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Cookie, X-CSRF-TOKEN, Accept, Authorization, X-XSRF-TOKEN, Form-type, Authori-zation');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PATCH,PUT,DELETE,OPTIONS,DELETE');
        $response->headers->set('Access-Control-Max-Age', '172800');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
