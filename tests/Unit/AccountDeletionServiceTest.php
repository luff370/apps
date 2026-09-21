<?php

namespace Tests\Unit;

use App\Models\Merchant;
use App\Models\SystemApp;
use App\Models\User;
use App\Services\User\AccountDeletionService;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    public function test_public_url_uses_package_name(): void
    {
        $service = app(AccountDeletionService::class);

        $this->assertSame('', $service->publicUrl(''));
        $this->assertStringEndsWith('/account-deletion/com.example.app', $service->publicUrl('com.example.app'));
    }

    public function test_anonymize_payload_removes_identity_fields(): void
    {
        $user = new User();
        $user->id = 12;
        $user->email = 'user@example.com';
        $user->account = 'hello';
        $user->uuid = 'device-uuid';

        $payload = app(AccountDeletionService::class)->anonymizePayload($user);

        $this->assertSame(1, $payload['is_del']);
        $this->assertSame('deleted_12', $payload['account']);
        $this->assertSame('deleted_12', $payload['uuid']);
        $this->assertSame('deleted_12@deleted.invalid', $payload['email']);
        $this->assertSame('', $payload['password']);
        $this->assertSame('Deleted User', $payload['nickname']);
        $this->assertArrayNotHasKey('alipay_user_id', $payload);
    }

    public function test_account_deletion_web_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        $show = $routes->getByName('account-deletion.show');
        $this->assertNotNull($show);
        $this->assertSame('App\Http\Controllers\Web\AccountDeletionController@show', $show->getAction('controller'));
        $this->assertSame('account-deletion/{app}', $show->uri());

        $store = $routes->getByName('account-deletion.store');
        $this->assertNotNull($store);
        $this->assertSame('App\Http\Controllers\Web\AccountDeletionController@store', $store->getAction('controller'));
        $this->assertContains('POST', $store->methods());
    }

    public function test_page_data_includes_developer_info(): void
    {
        $merchant = new Merchant();
        $merchant->name = '汉润信息技术（深圳）有限公司';
        $merchant->registered_address = '宝安区沙井街道后亭社区第二工业区58号A503';
        $merchant->corporate_phone = '';
        $merchant->contact_email = 'novelvault@appasd.com';

        $app = new SystemApp();
        $app->name = '内部应用名';
        $app->package_name = '';
        $app->logo = '';
        $app->contact_email = '';
        $app->markets = [
            ['market_channel' => 'huawei', 'name' => '华为渠道名'],
            ['market_channel' => 'google', 'name' => 'NovelVault'],
        ];
        $app->setRelation('merchant', $merchant);

        $data = app(AccountDeletionService::class)->pageData($app);

        $this->assertSame('内部应用名', $data['app_name']);
        $this->assertSame('NovelVault', $data['developer_name']);
        $this->assertSame('汉润信息技术（深圳）有限公司', $data['company_name']);
        $this->assertSame('novelvault@appasd.com', $data['contact_email']);
        $this->assertSame('宝安区沙井街道后亭社区第二工业区58号A503', $data['developer_address']);
    }

    public function test_google_channel_developer_name_is_empty_without_google_channel(): void
    {
        $app = new SystemApp();
        $app->name = '内部应用名';
        $app->package_name = '';
        $app->markets = [
            ['market_channel' => 'huawei', 'name' => '华为渠道名'],
        ];

        $this->assertSame(
            '',
            app(AccountDeletionService::class)->googleChannelDeveloperName($app)
        );
    }
}
