<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Services\ClientRequestContext;
use App\Support\Services\DeviceEnvHeaderAliasService;
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
        /*
         * 仅挂在客户端业务路由上（见 routes/api.php）。
         * 1. inspect() 读取并解密 Device-Env，生成统一的风险上下文；
         * 2. 风险上下文挂到 Request attributes，业务控制器无需重复解密；
         * 3. record() 将成功和失败结果写入审计表；
         * 4. 应用 ID、应用版本、市场渠道缺失时拒绝请求。Uuid 校验暂缓，上线观察后再打开。
         *
         * 混淆网关会把外层请求内部转发到真实路由。外层已经消费 nonce 并注入上下文时，
         * 内层必须直接复用，否则同一次 HTTP 请求会被第二次解析并判定为重放。
         */
        if (!$request->attributes->has('device_env_risk')) {
            $context = $this->riskService->inspect($request);
            $request->attributes->set('device_env_risk', $context);
            $this->auditService->record($request, $context);
        }

        $identityError = $this->missingIdentity($request);
        if ($identityError !== null) {
            $context = $request->attributes->get('device_env_risk', []);
            logger()->warning('客户端身份校验失败：' . $identityError, [
                'path' => $request->path(),
                'method' => $request->method(),
                'package_name' => ClientRequestContext::packageName($request),
                'has_app_id_header' => $request->headers->has('App-Id'),
                'has_uuid_header' => $request->headers->has('Uuid'),
                'has_device_env' => $request->headers->has('Device-Env')
                    || $this->hasAliasedDeviceEnv($request),
                'device_env_status' => $context['status'] ?? null,
                'device_env_error' => $context['error'] ?? null,
            ]);

            return response()->json(['status' => 400, 'code' => 400, 'msg' => $identityError, 'data' => null]);
        }

        return $next($request);
    }

    private function missingIdentity(Request $request): ?string
    {
        if (ClientRequestContext::appId($request) === null) {
            return '缺少应用信息';
        }
        if (ClientRequestContext::appVersion($request) === null) {
            return '缺少应用版本';
        }
        if (ClientRequestContext::marketChannel($request) === null) {
            return '缺少市场渠道';
        }
        if (ClientRequestContext::uuid($request) === null) {
            return '缺少设备标识UUID';
        }

        return null;
    }

    private function hasAliasedDeviceEnv(Request $request): bool
    {
        $packageName = (string) (ClientRequestContext::packageName($request) ?? '');
        $appId = (string) (ClientRequestContext::appId($request) ?? '');
        if ($packageName === '' || $appId === '' || !is_numeric($appId)) {
            return false;
        }
        $alias = (new DeviceEnvHeaderAliasService())->make((int) $appId, $packageName);

        return $request->headers->has($alias);
    }
}
