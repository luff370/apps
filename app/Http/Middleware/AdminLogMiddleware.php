<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Services\System\Log\SystemLogServices;

/**
 * 日志中間件
 * Class AdminLogMiddleware
 *
 * @package app\admin\middleware
 */
class AdminLogMiddleware
{
    /**
     * @param Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        try {
            /** @var SystemLogServices $services */
            $services = app(SystemLogServices::class);
            $services->recordAdminLog(request()->adminId(), request()->adminInfo()['account'], 'system');
        } catch (\Throwable $e) {
        }

        return $next($request);
    }
}
