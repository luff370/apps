<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Services\DeviceEnvRiskService;
use App\Support\Services\RiskProbeAuditService;

class DeviceEnvRiskMiddleware
{
    public function __construct(
        private DeviceEnvRiskService $riskService,
        private RiskProbeAuditService $auditService
    )
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 用户行为上报是独立 JSON 协议，不读取或审计 Device-Env。
        if ($request->is('api/user/behavior/report')) {
            return $next($request);
        }

        // 风控探针解析要早于业务控制器执行，但不和 api_obfuscation 的别名/字段映射耦合。
        // 如果请求来自混淆网关的内部转发，外层请求已经解析过并透传了 attribute，这里直接复用。
        if (!$request->attributes->has('device_env_risk')) {
            $context = $this->riskService->inspect($request);
            $request->attributes->set('device_env_risk', $context);
            $this->auditService->record($request, $context);
        }

        return $next($request);
    }
}
