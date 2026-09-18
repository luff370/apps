<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Services\ApiObfuscationProfileResolver;
use App\Support\Services\ClientRequestContext;
use App\Support\Services\ImagePathAliasService;

class ApiObfuscationMiddleware
{
    public function __construct(private ApiObfuscationProfileResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('X-Obfuscated-Gateway') === '1') {
            return $next($request);
        }

        $profile = $this->resolver->resolve($request);
        $request->attributes->set('api_obfuscation_profile', $profile);

        if (!($profile['enabled'] ?? false)) {
            return $next($request);
        }

        $decrypted = $this->tryDecryptRequestPacket($request, $profile);
        if ($decrypted instanceof JsonResponse) {
            return $decrypted;
        }

        $requestKeyMap = $this->resolveRequestKeyMap($request, $profile);
        if (!empty($requestKeyMap)) {
            // 映射表是「真实字段 => 别名」；客户端提交别名，需反查回真实字段。
            // 直接替换参数袋，不要 merge：否则别名字段还留在 json()/request 里。
            $this->replaceRequestInput($request, $requestKeyMap);
        }

        $response = $next($request);

        return $this->wrapJsonResponse($response, $profile, $request);
    }

    private function wrapJsonResponse(Response $response, array $profile, ?Request $request = null): Response
    {
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $request = $request ?: request();
        $payload = $response->getData(true);
        if (!is_array($payload)) {
            return $response;
        }

        $payload = $this->rewriteImageUrls($payload, $profile, $request);
        $routeAlias = (array) $request->attributes->get('api_obfuscation_route_alias', []);

        // 两层响应映射必须分开：
        // 1) 接口别名（及历史 response_data_key_map）只改 data 里面的字段；
        // 2) 应用配置里的 response_key_map 只改外层 status/msg/data。
        $responseDataKeyMap = $this->responseDataKeyMap($routeAlias, $profile);
        if (isset($payload['data']) && is_array($payload['data']) && !empty($responseDataKeyMap)) {
            $payload['data'] = $this->remapKeys($payload['data'], $responseDataKeyMap);
        }

        $responseKeyMap = (array) ($profile['response_key_map'] ?? []);
        if (!empty($responseKeyMap)) {
            $payload = $this->remapKeys($payload, $responseKeyMap, false);
        }

        $protocol = $profile['protocol'] ?? [];
        if (($protocol['encrypt_response'] ?? false) && config('api_obfuscation.encryption_enabled', false)) {
            return $this->encryptResponsePacket($payload, $response, $profile);
        }

        return new JsonResponse($payload, $response->getStatusCode(), $response->headers->all());
    }

    private function tryDecryptRequestPacket(Request $request, array $profile): ?JsonResponse
    {
        $protocol = $profile['protocol'] ?? [];
        $encryptRequest = (bool) ($protocol['encrypt_request'] ?? false);
        if (!$encryptRequest || !config('api_obfuscation.encryption_enabled', false)) {
            return null;
        }

        $payloadField = (string) ($protocol['payload_field'] ?? 'payload');
        $signField = (string) ($protocol['sign_field'] ?? 'sign');
        $timestampField = (string) ($protocol['timestamp_field'] ?? 'ts');
        $nonceField = (string) ($protocol['nonce_field'] ?? 'nonce');
        $allowPlaintext = (bool) ($protocol['allow_plaintext_request'] ?? true);

        $encryptedPayload = (string) $request->input($payloadField, '');
        if ($encryptedPayload === '') {
            return $allowPlaintext ? null : $this->packetError('missing encrypted payload');
        }

        $timestamp = (string) $request->input($timestampField, '');
        $nonce = (string) $request->input($nonceField, '');
        $sign = (string) $request->input($signField, '');
        if ($timestamp === '' || $nonce === '' || $sign === '') {
            return $this->packetError('missing sign fields');
        }

        if (!$this->validateTimestamp((int) $timestamp, $profile)) {
            return $this->packetError('request expired');
        }

        if (!$this->consumeNonce($request, $nonce, $profile)) {
            return $this->packetError('replayed request');
        }

        $signPayload = $this->buildSignPayload($encryptedPayload, $timestamp, $nonce);
        if (!$this->verifySign($signPayload, $sign, $profile)) {
            return $this->packetError('invalid sign');
        }

        try {
            $decoded = $this->decryptJson($encryptedPayload, $profile);
            if (!is_array($decoded)) {
                return $this->packetError('invalid payload');
            }
            $request->merge($decoded);
        } catch (Throwable $e) {
            return $this->packetError('decrypt failed');
        }

        return null;
    }

    private function encryptResponsePacket(array $payload, JsonResponse $response, array $profile): JsonResponse
    {
        $protocol = $profile['protocol'] ?? [];
        $payloadField = (string) ($protocol['payload_field'] ?? 'payload');
        $signField = (string) ($protocol['sign_field'] ?? 'sign');
        $timestampField = (string) ($protocol['timestamp_field'] ?? 'ts');
        $nonceField = (string) ($protocol['nonce_field'] ?? 'nonce');
        $versionField = (string) ($protocol['version_field'] ?? 'ver');

        $timestamp = (string) time();
        $nonce = Str::random(24);
        $encryptedPayload = $this->encryptJson($payload, $profile);
        $signPayload = $this->buildSignPayload($encryptedPayload, $timestamp, $nonce);
        $sign = $this->sign($signPayload, $profile);

        $packet = [
            $payloadField => $encryptedPayload,
            $signField => $sign,
            $timestampField => $timestamp,
            $nonceField => $nonce,
            $versionField => (string) config('api_obfuscation.packet_version', '1'),
        ];

        return new JsonResponse($packet, $response->getStatusCode(), $response->headers->all());
    }

    private function packetError(string $msg): JsonResponse
    {
        return response()->json(['status' => 400, 'msg' => $msg], 400);
    }

    private function validateTimestamp(int $timestamp, array $profile): bool
    {
        $window = (int) ($profile['security']['timestamp_window_seconds'] ?? config('api_obfuscation.timestamp_window_seconds', 300));
        return $timestamp > 0 && abs(time() - $timestamp) <= $window;
    }

    private function consumeNonce(Request $request, string $nonce, array $profile): bool
    {
        $ttl = (int) ($profile['security']['nonce_ttl_seconds'] ?? config('api_obfuscation.nonce_ttl_seconds', 300));
        $prefix = (string) config('api_obfuscation.nonce_cache_prefix', 'api_obf_nonce:');
        $appId = ClientRequestContext::appId($request)
            ?: (string) $request->header('Package-Name', 'default');
        $cacheKey = $prefix . $appId . ':' . sha1($nonce);

        return Cache::add($cacheKey, 1, $ttl);
    }

    private function buildSignPayload(string $payload, string $timestamp, string $nonce): string
    {
        return $payload . '|' . $timestamp . '|' . $nonce;
    }

    private function sign(string $text, array $profile): string
    {
        $signKey = (string) ($profile['crypto']['sign_key'] ?? config('crypto.sign_key', ''));
        return hash_hmac('sha256', $text, $signKey);
    }

    private function verifySign(string $text, string $sign, array $profile): bool
    {
        return hash_equals($this->sign($text, $profile), $sign);
    }

    private function encryptJson(array $payload, array $profile): string
    {
        $cipher = (string) ($profile['crypto']['cipher'] ?? 'AES-256-CBC');
        $key = base64_decode((string) ($profile['crypto']['key'] ?? config('crypto.key')), true);
        $iv = base64_decode((string) ($profile['crypto']['iv'] ?? config('crypto.iv')), true);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $encrypted = openssl_encrypt($json, $cipher, $key ?: '', OPENSSL_RAW_DATA, $iv ?: '');

        return base64_encode($encrypted ?: '');
    }

    private function decryptJson(string $encryptedBase64, array $profile): array
    {
        $cipher = (string) ($profile['crypto']['cipher'] ?? 'AES-256-CBC');
        $key = base64_decode((string) ($profile['crypto']['key'] ?? config('crypto.key')), true);
        $iv = base64_decode((string) ($profile['crypto']['iv'] ?? config('crypto.iv')), true);
        $encrypted = base64_decode($encryptedBase64, true);
        if ($encrypted === false) {
            throw new \RuntimeException('invalid base64 payload');
        }

        $decrypted = openssl_decrypt($encrypted ?: '', $cipher, $key ?: '', OPENSSL_RAW_DATA, $iv ?: '');
        if ($decrypted === false) {
            throw new \RuntimeException('decrypt failed');
        }

        $decoded = json_decode((string) $decrypted, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('invalid decrypted json');
        }

        return $decoded;
    }

    private function rewriteImageUrls(array $payload, array $profile, Request $request): array
    {
        $globalEnabled = (bool) config('api_obfuscation.image_url_rewrite_enabled', true);
        $config = $profile['image_url'] ?? [];
        // 域名替换和路径别名是两个独立开关：前者只换 host，后者只把 storage/attach 换成按应用生成的别名段。
        // 两个开关都关时不扫描响应。
        $domainEnabled = (bool) ($config['enabled'] ?? $profile['image_url_enabled'] ?? false);
        $pathAliasEnabled = (bool) ($config['path_alias_enabled'] ?? $profile['image_path_alias_enabled'] ?? false);
        if (!$globalEnabled || (!$domainEnabled && !$pathAliasEnabled)) {
            return $payload;
        }

        $host = '';
        if ($domainEnabled) {
            $host = $this->imageRewriteHost((string) ($profile['image_domain'] ?? $config['domain'] ?? config('api_obfuscation.default_image_domain', '')));
            if ($host === '') {
                $host = (string) $request->getHost();
            }
        }

        $imageDefaults = (array) config('api_obfuscation.profiles.default.image_url', []);
        $prefixes = (array) ($imageDefaults['path_prefixes'] ?? ['attach/', '/attach/', 'uploads/attach/', '/uploads/attach/', 'storage/attach/', '/storage/attach/']);

        $pathRewriter = null;
        if ($pathAliasEnabled) {
            $aliasService = new ImagePathAliasService();
            $appId = (int) ($profile['app_id'] ?? 0);
            $packageName = (string) ($profile['package_name'] ?? '');
            $pathRewriter = fn (string $path, string $matchedPrefix): string
                => $aliasService->replacePrefix($path, $matchedPrefix, $appId, $packageName);
        }

        return $this->rewriteImagesRecursively($payload, $host, $prefixes, $pathRewriter);
    }

    private function rewriteImagesRecursively(array $payload, string $host, array $prefixes, ?callable $pathRewriter = null): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->rewriteImagesRecursively($value, $host, $prefixes, $pathRewriter);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $rewritten = $this->rewriteSingleImageUrl($value, $host, $prefixes, $pathRewriter);
            if ($rewritten !== null) {
                $payload[$key] = $rewritten;
            }
        }

        return $payload;
    }

    private function rewriteSingleImageUrl(string $value, string $host, array $prefixes, ?callable $pathRewriter = null): ?string
    {
        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            return null;
        }

        $normalized = str_replace('\\', '/', $value);
        $path = (string) (parse_url($normalized, PHP_URL_PATH) ?? '');
        $matched = $this->matchImagePrefix($path, $prefixes);
        if ($matched === null) {
            return null;
        }

        $query = (string) (parse_url($normalized, PHP_URL_QUERY) ?? '');
        if ($pathRewriter) {
            $path = $pathRewriter($path, $matched);
        }

        $origin = $host !== '' ? $this->replaceUrlHost($normalized, $host) : $this->urlOrigin($normalized);
        $target = rtrim($origin, '/') . '/' . ltrim($path, '/');

        return $query !== '' ? $target . '?' . $query : $target;
    }

    /**
     * 配置里的图片域名只取 host，http/https、多余路径都不参与替换。
     */
    private function imageRewriteHost(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#^//#', '', $domain) ?? $domain;
        $domain = trim($domain, '/');
        if ($domain === '') {
            return '';
        }
        $host = explode('/', $domain, 2)[0];

        return strtolower($host);
    }

    /**
     * 只替换 host（及可选端口），保留原 URL 的 http / https / 协议相对写法。
     */
    private function replaceUrlHost(string $url, string $host): string
    {
        if (str_starts_with($url, '//')) {
            return '//' . $host;
        }

        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?? '');

        return ($scheme !== '' ? $scheme . ':' : '') . '//' . $host;
    }

    /**
     * 返回命中的最长前缀，供路径别名替换时精确截断。
     */
    private function matchImagePrefix(string $value, array $prefixes): ?string
    {
        $matched = null;
        foreach ($prefixes as $prefix) {
            $prefix = (string) $prefix;
            if ($prefix === '' || !str_starts_with($value, $prefix)) {
                continue;
            }
            if ($matched === null || strlen($prefix) > strlen($matched)) {
                $matched = $prefix;
            }
        }

        return $matched;
    }

    private function urlOrigin(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        if ($host === '') {
            return '';
        }

        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?? '');
        $port = parse_url($url, PHP_URL_PORT);
        $origin = ($scheme !== '' ? $scheme . ':' : '') . '//' . $host;

        return $port ? $origin . ':' . $port : $origin;
    }

    private function responseDataKeyMap(array $routeAlias, array $profile): array
    {
        foreach (['response_data_key_map', 'response_key_map'] as $field) {
            $map = (array) ($routeAlias[$field] ?? []);
            if (!empty($map)) {
                return $map;
            }
        }

        return (array) ($profile['response_data_key_map'] ?? []);
    }

    private function resolveRequestKeyMap(Request $request, array $profile): array
    {
        $alias = (string) ($request->route()?->parameter('alias') ?? '');
        $routeAlias = (array) (($profile['route_aliases'][$alias] ?? []) ?: []);
        $perAliasMap = (array) ($routeAlias['request_key_map'] ?? []);
        $profileMap = (array) ($profile['request_key_map'] ?? []);

        return array_merge($profileMap, $perAliasMap);
    }

    private function remapKeys(array $source, array $map, bool $deep = true): array
    {
        if (empty($map)) {
            return $source;
        }

        $target = [];
        foreach ($source as $key => $value) {
            $mappedKey = $map[$key] ?? $key;
            $target[$mappedKey] = ($deep && is_array($value)) ? $this->remapKeys($value, $map, true) : $value;
        }

        return $target;
    }

    private function replaceRequestInput(Request $request, array $requestKeyMap): void
    {
        $input = $request->isJson() ? $request->json() : $request->request;
        $input->replace($this->unmapKeys($input->all(), $requestKeyMap));
        $request->query->replace($this->unmapKeys($request->query->all(), $requestKeyMap));
    }

    private function unmapKeys(array $source, array $map): array
    {
        if (empty($map)) {
            return $source;
        }

        $reverseMap = array_flip($map);
        $target = [];
        foreach ($source as $key => $value) {
            $mappedKey = $reverseMap[$key] ?? $key;
            $target[$mappedKey] = is_array($value) ? $this->unmapKeys($value, $map) : $value;
        }

        return $target;
    }
}
