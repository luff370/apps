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

        $request->merge($this->remapKeys($request->all(), $profile['request_key_map'] ?? []));

        $response = $next($request);

        return $this->wrapJsonResponse($response, $profile);
    }

    private function wrapJsonResponse(Response $response, array $profile): Response
    {
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        if (!is_array($payload)) {
            return $response;
        }

        $payload = $this->rewriteImageUrls($payload, $profile, request());

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = $this->remapKeys($payload['data'], $profile['response_data_key_map'] ?? []);
        }

        $payload = $this->remapKeys($payload, $profile['response_key_map'] ?? []);

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
        $appId = (string) $request->header('App-Id', 'default');
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
        $enabled = (bool) ($config['enabled'] ?? false);
        if (!$globalEnabled || !$enabled) {
            return $payload;
        }

        $domain = (string) ($config['domain'] ?? config('api_obfuscation.default_image_domain', ''));
        if ($domain === '') {
            $domain = rtrim((string) $request->getSchemeAndHttpHost(), '/');
        }

        $fields = $config['fields'] ?? [];
        $prefixes = $config['path_prefixes'] ?? ['attach/', '/attach/', 'uploads/attach/', '/uploads/attach/'];

        return $this->rewriteImagesRecursively($payload, $domain, $fields, $prefixes);
    }

    private function rewriteImagesRecursively(array $payload, string $domain, array $fields, array $prefixes): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->rewriteImagesRecursively($value, $domain, $fields, $prefixes);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $shouldCheck = empty($fields) || in_array((string) $key, $fields, true);
            if (!$shouldCheck || $this->isAbsoluteUrl($value)) {
                continue;
            }

            $normalized = str_replace('\\', '/', $value);
            foreach ($prefixes as $prefix) {
                if (str_starts_with($normalized, $prefix)) {
                    $payload[$key] = rtrim($domain, '/') . '/' . ltrim($normalized, '/');
                    break;
                }
            }
        }

        return $payload;
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '//')
            || str_starts_with($value, 'data:');
    }

    private function remapKeys(array $source, array $map): array
    {
        if (empty($map)) {
            return $source;
        }

        $target = [];
        foreach ($source as $key => $value) {
            $mappedKey = $map[$key] ?? $key;
            $target[$mappedKey] = is_array($value) ? $this->remapKeys($value, $map) : $value;
        }

        return $target;
    }
}
