<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Http\Kernel;
use App\Support\Services\ApiObfuscationProfileResolver;

class ObfuscatedGatewayController extends Controller
{
    public function __construct(
        private ApiObfuscationProfileResolver $resolver,
        private Kernel $kernel
    ) {
    }

    public function dispatch(Request $request, string $alias, string $params = '')
    {
        $profile = $this->resolver->resolve($request);
        if (!($profile['enabled'] ?? false)) {
            return $this->fail('invalid request', null, 404);
        }

        $aliasRoute = $profile['route_aliases'][$alias] ?? null;
        if (!$aliasRoute || empty($aliasRoute['path'])) {
            return $this->fail('invalid route alias', null, 404);
        }

        $targetPath = ltrim((string) $aliasRoute['path'], '/');
        if (str_starts_with($targetPath, 'v/')) {
            return $this->fail('invalid route target', null, 400);
        }

        $targetPath = $this->fillRouteParameters($targetPath, $params);
        $targetMethod = strtoupper((string) ($aliasRoute['method'] ?? $request->getMethod()));
        $forwardRequest = Request::create(
            '/api/' . $targetPath,
            $targetMethod,
            $request->all(),
            $request->cookies->all(),
            $request->allFiles(),
            $request->server->all(),
            $request->getContent()
        );
        $forwardRequest->headers->replace($request->headers->all());
        $forwardRequest->headers->set('X-Obfuscated-Gateway', '1');
        // 外层 /api/{prefix}/{alias} 已经消费过 Device-Env nonce。
        // 内部转发到真实接口时复用同一个风控上下文，避免同一次请求被判定为重放。
        if ($request->attributes->has('device_env_risk')) {
            $forwardRequest->attributes->set('device_env_risk', $request->attributes->get('device_env_risk'));
        }

        return $this->kernel->handle($forwardRequest);
    }

    private function fillRouteParameters(string $targetPath, string $params): string
    {
        if ($params === '' || !str_contains($targetPath, '{')) {
            return $targetPath;
        }

        $segments = array_values(array_filter(explode('/', trim($params, '/')), fn($item) => $item !== ''));
        $index = 0;

        return preg_replace_callback('/\{[^}]+\}/', function () use (&$segments, &$index) {
            return $segments[$index++] ?? '';
        }, $targetPath) ?? $targetPath;
    }
}
