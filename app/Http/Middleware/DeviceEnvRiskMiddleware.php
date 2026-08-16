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

        /*
         * API 风控主入口：
         * 1. inspect() 读取并解密 Device-Env，生成统一的风险上下文；
         * 2. 风险上下文挂到 Request attributes，业务控制器无需重复解密；
         * 3. record() 将成功、缺失和失败结果都写入审计表，供后续按设备分析；
         * 4. 无论解析或落库结果如何，默认继续执行原业务，避免旧客户端被误伤。
         *
         * 混淆网关会把外层请求内部转发到真实路由。外层已经消费 nonce 并注入上下文时，
         * 内层必须直接复用，否则同一次 HTTP 请求会被第二次解析并判定为重放。
         */
        if (!$request->attributes->has('device_env_risk')) {
            $context = $this->riskService->inspect($request);
            $request->attributes->set('device_env_risk', $context);
            $this->auditService->record($request, $context);
        }

        return $next($request);
    }
}
