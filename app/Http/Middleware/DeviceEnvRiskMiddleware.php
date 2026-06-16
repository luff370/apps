<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Services\DeviceEnvRiskService;

class DeviceEnvRiskMiddleware
{
    public function __construct(private DeviceEnvRiskService $riskService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 风控探针解析要早于业务控制器执行，但不和 api_obfuscation 的别名/字段映射耦合。
        // 如果请求来自混淆网关的内部转发，外层请求已经解析过并透传了 attribute，这里直接复用。
        if (!$request->attributes->has('device_env_risk')) {
            $request->attributes->set('device_env_risk', $this->riskService->inspect($request));
        }

        return $next($request);
    }
}
