<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function handle(Request $request, Closure $next)
    {
        $this->preferRealIp($request);

        return parent::handle($request, $next);
    }

    /**
     * 经代理转发的域名会带 X-Real-IP。有合法值时作为客户端 IP，
     * 这样 $request->ip() 和 getClientIp() 不用逐个改业务代码。
     */
    private function preferRealIp(Request $request): void
    {
        $realIp = trim(strtok((string) $request->headers->get('X-Real-IP'), ',') ?: '');
        if (filter_var($realIp, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $request->attributes->set('original_remote_addr', $request->server->get('REMOTE_ADDR'));
        $request->server->set('REMOTE_ADDR', $realIp);
    }
}
