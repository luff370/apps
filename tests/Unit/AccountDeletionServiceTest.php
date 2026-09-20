<?php

namespace Tests\Unit;

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
}
