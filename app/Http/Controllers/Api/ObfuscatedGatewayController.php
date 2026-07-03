<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Http\Kernel;
use App\Support\Services\ApiObfuscationProfileResolver;

class ObfuscatedGatewayController extends Controller
{
    private const GATEWAY_SUFFIX_WORDS = [
        'atlas', 'bridge', 'center', 'cloud', 'field', 'flow', 'garden', 'harbor',
        'hub', 'lane', 'light', 'matrix', 'orbit', 'portal', 'river', 'stone',
        'stream', 'summit', 'tower', 'valley', 'wave', 'zone',
    ];

    public function __construct(
        private ApiObfuscationProfileResolver $resolver,
        private Kernel $kernel
    ) {
    }

    public function dispatchDynamic(Request $request, string $gatewayPrefix, string $gatewaySuffix, string $alias, string $params = '')
    {
        return $this->dispatch($request, $alias, $params);
    }

    public function dispatch(Request $request, string $alias, string $params = '')
    {
        $profile = $this->resolver->resolve($request);
        if (!($profile['enabled'] ?? false)) {
            return $this->fail('invalid request', null, 404);
        }

        if (!$this->isAllowedGatewayPrefix($request, $profile)) {
            return $this->fail('invalid gateway prefix', null, 404);
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

    private function isAllowedGatewayPrefix(Request $request, array $profile): bool
    {
        $prefix = $this->requestGatewayPrefix($request, $profile);
        if ($prefix === '') {
            return false;
        }

        // 兼容旧版已下发的固定网关前缀，例如 /api/open/{alias}。
        // 新版导出使用应用级动态前缀，但旧配置未更新前不能影响线上访问。
        $fixedPrefixes = array_map(
            fn($item) => trim((string) $item, '/'),
            config('api_obfuscation.gateway_prefixes', ['gateway'])
        );
        if (in_array($prefix, $fixedPrefixes, true)) {
            return true;
        }

        return $prefix === $this->gatewayPrefixSegmentForProfile($profile);
    }

    private function gatewayPrefixSegmentForProfile(array $profile): string
    {
        $prefixes = array_values(array_filter(config('api_obfuscation.gateway_prefixes', ['gateway'])));
        if (empty($prefixes)) {
            $prefixes = ['gateway'];
        }

        $identity = (string) ($profile['app_id'] ?? '') . '|' . (string) ($profile['package_name'] ?? '');
        $base = (string) $prefixes[abs(crc32($identity)) % count($prefixes)];
        $base = preg_replace('/[^a-zA-Z0-9]/', '', trim($base));
        $base = $base !== '' ? strtolower($base) : 'gateway';

        return $base . '/' . $this->gatewaySuffix($profile);
    }

    private function gatewaySuffix(array $profile): string
    {
        $identity = (string) ($profile['app_id'] ?? '') . '|' . (string) ($profile['package_name'] ?? '');
        $first = abs(crc32($identity . '|gateway_suffix:first')) % count(self::GATEWAY_SUFFIX_WORDS);
        $second = abs(crc32($identity . '|gateway_suffix:second')) % count(self::GATEWAY_SUFFIX_WORDS);
        if ($second === $first) {
            $second = ($second + 1) % count(self::GATEWAY_SUFFIX_WORDS);
        }

        return self::GATEWAY_SUFFIX_WORDS[$first] . self::GATEWAY_SUFFIX_WORDS[$second];
    }

    private function requestGatewayPrefix(Request $request, array $profile): string
    {
        $first = trim((string) $request->segment(2), '/');
        $second = trim((string) $request->segment(3), '/');
        if ($first === '') {
            return '';
        }

        $fixedPrefixes = array_map(
            fn($item) => trim((string) $item, '/'),
            config('api_obfuscation.gateway_prefixes', ['gateway'])
        );
        if ($second !== '' && $this->isDynamicGatewayRequest($request, $fixedPrefixes)) {
            return $first . '/' . $second;
        }

        return $first;
    }

    private function isDynamicGatewayRequest(Request $request, array $fixedPrefixes): bool
    {
        $first = trim((string) $request->segment(2), '/');
        $second = trim((string) $request->segment(3), '/');
        $third = trim((string) $request->segment(4), '/');
        if ($first === '' || $second === '' || $third === '') {
            return false;
        }

        return in_array($first, $fixedPrefixes, true);
    }
}
