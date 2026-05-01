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

    public function dispatch(Request $request, string $alias)
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

        return $this->kernel->handle($forwardRequest);
    }
}
