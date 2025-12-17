<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Services\System\Admin\AdminAuthServices;
use App\Services\System\Admin\SystemRoleServices;

/**
 * 后台登陆验证中间件
 * Class AdminAuthTokenMiddleware
 *
 * @package app\admin\middleware
 */
class AdminAuthMiddleware
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \throwable
     */
    public function handle(Request $request, \Closure $next)
    {
        $token = trim(ltrim($request->header(config('cookie.token_name', 'Authori-zation')), 'Bearer'));

        /** @var AdminAuthServices $service */
        $service = app(AdminAuthServices::class);
        $adminInfo = $service->parseToken($token);

        Request::macro('adminType', function () use (&$adminInfo) {
            return $adminInfo['account_type'];
        });

        Request::macro('adminId', function () use (&$adminInfo) {
            return $adminInfo['id'];
        });

        Request::macro('adminInfo', function () use (&$adminInfo) {
            return $adminInfo;
        });

        if ($request->adminInfo()['level']) {
            /** @var SystemRoleServices $systemRoleService */
            $systemRoleService = app()->make(SystemRoleServices::class);
            $systemRoleService->verifyAuth($request);
        }

        return $next($request);
    }
}
