<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;
use Illuminate\Http\Request;
use App\Support\Services\ImagePathAliasService;
use App\Http\Middleware\ApiObfuscationMiddleware;

class ImagePathAliasTest extends TestCase
{
    private const IMAGE_URL = 'http://storeimg.appasd.com/storage/attach/2026/03/RHW424Ze.png';

    public function test_alias_is_stable_per_app_package_and_prefix(): void
    {
        $service = new ImagePathAliasService();

        $first = $service->make(10036, 'com.example.app', 'storage/attach');
        $same = $service->make(10036, 'com.example.app', '/storage/attach/');
        $otherApp = $service->make(10037, 'com.example.app', 'storage/attach');
        $otherPackage = $service->make(10036, 'com.other.app', 'storage/attach');
        $otherPrefix = $service->make(10036, 'com.example.app', 'uploads/attach');

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $otherApp);
        $this->assertNotSame($first, $otherPackage);
        $this->assertNotSame($first, $otherPrefix);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $first);
    }

    public function test_replace_prefix_keeps_date_and_file_tail(): void
    {
        $service = new ImagePathAliasService();
        $alias = $service->make(10036, 'com.example.app', '/storage/attach/');

        $this->assertSame(
            '/' . $alias . '/2026/03/RHW424Ze.png',
            $service->replacePrefix('/storage/attach/2026/03/RHW424Ze.png', '/storage/attach/', 10036, 'com.example.app')
        );
    }

    public function test_path_alias_rewrites_only_the_prefix_segment(): void
    {
        $alias = (new ImagePathAliasService())->make(10036, 'com.example.app', '/storage/attach/');

        $payload = $this->rewrite([
            'path_alias_enabled' => true,
        ]);

        $this->assertSame(
            'http://storeimg.appasd.com/' . $alias . '/2026/03/RHW424Ze.png',
            $payload['data']['image']
        );
    }

    public function test_two_apps_get_different_urls_for_the_same_file(): void
    {
        $first = $this->rewrite(['path_alias_enabled' => true]);
        $second = $this->rewrite(['path_alias_enabled' => true], 10037, 'com.other.app');

        $this->assertNotSame($first['data']['image'], $second['data']['image']);
        $this->assertMatchesRegularExpression(
            '#^http://storeimg\.appasd\.com/[a-z0-9]{8}/2026/03/RHW424Ze\.png$#',
            $second['data']['image']
        );
    }

    public function test_domain_switch_alone_keeps_the_real_path(): void
    {
        $payload = $this->rewrite([
            'enabled' => true,
            'domain' => 'http://cdn.example.com',
        ]);

        $this->assertSame(
            'http://cdn.example.com/storage/attach/2026/03/RHW424Ze.png',
            $payload['data']['image']
        );
    }

    public function test_domain_replace_ignores_scheme_in_config(): void
    {
        $httpConfig = $this->rewrite([
            'enabled' => true,
            'domain' => 'https://cdn.example.com',
        ]);
        $bareConfig = $this->rewrite([
            'enabled' => true,
            'domain' => 'cdn.example.com/',
        ]);

        $this->assertSame(
            'http://cdn.example.com/storage/attach/2026/03/RHW424Ze.png',
            $httpConfig['data']['image']
        );
        $this->assertSame(
            'http://cdn.example.com/storage/attach/2026/03/RHW424Ze.png',
            $bareConfig['data']['image']
        );
    }

    public function test_image_urls_are_rewritten_without_field_name_filter(): void
    {
        $payload = $this->rewrite([
            'enabled' => true,
            'domain' => 'cdn.example.com',
        ], 10036, 'com.example.app', [
            'status' => 200,
            'data' => [
                'head_img' => self::IMAGE_URL,
                'list' => [
                    ['cover_url' => self::IMAGE_URL],
                ],
            ],
        ]);

        $this->assertSame(
            'http://cdn.example.com/storage/attach/2026/03/RHW424Ze.png',
            $payload['data']['head_img']
        );
        $this->assertSame(
            'http://cdn.example.com/storage/attach/2026/03/RHW424Ze.png',
            $payload['data']['list'][0]['cover_url']
        );
    }

    public function test_both_switches_replace_domain_and_prefix(): void
    {
        $alias = (new ImagePathAliasService())->make(10036, 'com.example.app', '/storage/attach/');

        $payload = $this->rewrite([
            'enabled' => true,
            'domain' => 'http://cdn.example.com',
            'path_alias_enabled' => true,
        ]);

        $this->assertSame(
            'http://cdn.example.com/' . $alias . '/2026/03/RHW424Ze.png',
            $payload['data']['image']
        );
    }

    public function test_both_switches_off_leaves_payload_untouched(): void
    {
        $payload = $this->rewrite([]);

        $this->assertSame(self::IMAGE_URL, $payload['data']['image']);
    }

    public function test_non_image_urls_are_not_touched(): void
    {
        $payload = $this->rewrite(['path_alias_enabled' => true]);

        $this->assertSame('http://storeimg.appasd.com/other/2026/03/x.png', $payload['data']['other']);
    }

    private function rewrite(array $imageConfig, int $appId = 10036, string $packageName = 'com.example.app', ?array $payload = null): array
    {
        $middleware = (new ReflectionClass(ApiObfuscationMiddleware::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ApiObfuscationMiddleware::class))->getMethod('rewriteImageUrls');
        $method->setAccessible(true);

        $profile = [
            'app_id' => $appId,
            'package_name' => $packageName,
            'image_url' => array_merge([
                'enabled' => false,
                'domain' => '',
                'path_alias_enabled' => false,
            ], $imageConfig),
        ];

        $payload ??= [
            'status' => 200,
            'data' => [
                'image' => self::IMAGE_URL,
                'other' => 'http://storeimg.appasd.com/other/2026/03/x.png',
            ],
        ];

        return $method->invoke($middleware, $payload, $profile, Request::create('/api/app/info', 'POST'));
    }
}
