<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\App\AppApiObfuscationService;
use App\Http\Middleware\ApiObfuscationMiddleware;

class ApiObfuscationResponseAliasTest extends TestCase
{
    public function test_response_alias_generation_skips_outer_envelope_and_includes_list_item_fields(): void
    {
        $service = $this->newService();
        $method = $this->method(AppApiObfuscationService::class, 'responseParamKeysForAlias');

        $params = [
            'code' => 200,
            'message' => 'success',
            'result' => [
                [
                    'id' => 12,
                    'lang' => 'en',
                    'name' => '正能量提现',
                    'sort' => 999,
                ],
            ],
        ];
        $profile = [
            'app_id' => 10002,
            'package_name' => 'com.demo.app',
            'response_key_map' => ['status' => 's', 'msg' => 'm', 'data' => 'd'],
        ];

        $keys = $method->invoke($service, $params, $profile);

        $this->assertNotContains('code', $keys);
        $this->assertNotContains('message', $keys);
        $this->assertNotContains('result', $keys);
        $this->assertContains('id', $keys);
        $this->assertContains('lang', $keys);
        $this->assertContains('name', $keys);
        $this->assertContains('sort', $keys);
    }

    public function test_response_alias_generation_reads_list_item_fields_even_when_type_exists(): void
    {
        $service = $this->newService();
        $method = $this->method(AppApiObfuscationService::class, 'responseParamKeysForAlias');

        $params = [
            'code' => 200,
            'message' => 'success',
            'result' => [
                [
                    'id' => 12,
                    'lang' => 'en',
                    'name' => '正能量提现',
                    'type' => 'withdrawal',
                    'price' => 0.01,
                ],
            ],
        ];
        $profile = [
            'app_id' => 10002,
            'package_name' => 'com.demo.app',
            'response_key_map' => ['status' => 's', 'msg' => 'm', 'data' => 'd'],
        ];

        $keys = $method->invoke($service, $params, $profile);

        $this->assertNotContains('code', $keys);
        $this->assertNotContains('result', $keys);
        $this->assertContains('id', $keys);
        $this->assertContains('name', $keys);
        $this->assertContains('type', $keys);
        $this->assertContains('price', $keys);
    }

    public function test_response_alias_generation_reads_nested_schema_items(): void
    {
        $service = $this->newService();
        $method = $this->method(AppApiObfuscationService::class, 'responseParamKeysForAlias');

        $params = [
            ['key' => 'status', 'type' => 'int'],
            ['key' => 'msg', 'type' => 'string'],
            [
                'key' => 'data',
                'type' => 'array',
                'items' => [
                    ['key' => 'id', 'type' => 'int'],
                    ['key' => 'name', 'type' => 'string'],
                ],
            ],
        ];
        $profile = [
            'app_id' => 10002,
            'package_name' => 'com.demo.app',
            'response_key_map' => ['status' => 's', 'msg' => 'm', 'data' => 'd'],
        ];

        $keys = $method->invoke($service, $params, $profile);

        $this->assertSame(['id', 'name'], $keys);
    }

    public function test_param_alias_is_stable_when_json_field_order_changes(): void
    {
        $service = $this->newService();
        $method = $this->method(AppApiObfuscationService::class, 'stableParamsMap');
        $profile = ['app_id' => 10002, 'package_name' => 'com.demo.app', 'response_key_map' => ['status' => 's', 'msg' => 'm', 'data' => 'd']];

        $first = $method->invoke($service, [
            'result' => [['id' => 1, 'name' => 'a', 'price' => 1]],
        ], $profile, 'response');
        $reordered = $method->invoke($service, [
            'result' => [['price' => 1, 'extra' => 'x', 'name' => 'a', 'id' => 1]],
        ], $profile, 'response');

        $this->assertSame($first['id'], $reordered['id']);
        $this->assertSame($first['name'], $reordered['name']);
        $this->assertSame($first['price'], $reordered['price']);
        $this->assertArrayHasKey('extra', $reordered);
        $this->assertArrayNotHasKey('extra', $first);
    }

    public function test_list_response_item_fields_are_remapped_in_gateway_response(): void
    {
        $middleware = (new ReflectionClass(ApiObfuscationMiddleware::class))->newInstanceWithoutConstructor();
        $wrap = $this->method(ApiObfuscationMiddleware::class, 'wrapJsonResponse');

        $request = Request::create('/api/open/abc12345', 'POST');
        $request->attributes->set('api_obfuscation_route_alias', [
            'response_key_map' => ['id' => 'p1a2b3', 'name' => 'p4c5d6'],
        ]);

        $response = new JsonResponse([
            'status' => 200,
            'msg' => 'success',
            'data' => [
                ['id' => 12, 'name' => 'item-a'],
                ['id' => 13, 'name' => 'item-b'],
            ],
        ]);

        $result = $wrap->invoke($middleware, $response, [
            'response_key_map' => ['status' => 's', 'msg' => 'm', 'data' => 'd'],
            'protocol' => ['encrypt_response' => true],
        ], $request);

        $this->assertSame([
            's' => 200,
            'm' => 'success',
            'd' => [
                ['p1a2b3' => 12, 'p4c5d6' => 'item-a'],
                ['p1a2b3' => 13, 'p4c5d6' => 'item-b'],
            ],
        ], $result->getData(true));
    }

    public function test_result_list_item_fields_are_remapped_in_gateway_response(): void
    {
        $middleware = (new ReflectionClass(ApiObfuscationMiddleware::class))->newInstanceWithoutConstructor();
        $wrap = $this->method(ApiObfuscationMiddleware::class, 'wrapJsonResponse');

        $request = Request::create('/api/open/abc12345', 'POST');
        $request->attributes->set('api_obfuscation_route_alias', [
            'response_key_map' => ['id' => 'p1a2b3', 'name' => 'p4c5d6'],
        ]);

        $response = new JsonResponse([
            'code' => 200,
            'message' => 'success',
            'result' => [
                ['id' => 12, 'name' => 'item-a'],
            ],
        ]);

        $result = $wrap->invoke($middleware, $response, [
            'response_key_map' => ['code' => 's', 'message' => 'm', 'result' => 'd'],
            'protocol' => ['encrypt_response' => true],
        ], $request);

        $this->assertSame([
            's' => 200,
            'm' => 'success',
            'd' => [
                ['p1a2b3' => 12, 'p4c5d6' => 'item-a'],
            ],
        ], $result->getData(true));
    }

    private function newService(): AppApiObfuscationService
    {
        return (new ReflectionClass(AppApiObfuscationService::class))->newInstanceWithoutConstructor();
    }

    private function method(string $class, string $method): \ReflectionMethod
    {
        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }
}
