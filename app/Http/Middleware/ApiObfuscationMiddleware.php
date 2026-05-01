<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = $this->remapKeys($payload['data'], $profile['response_data_key_map'] ?? []);
        }

        $payload = $this->remapKeys($payload, $profile['response_key_map'] ?? []);

        return new JsonResponse($payload, $response->getStatusCode(), $response->headers->all());
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
