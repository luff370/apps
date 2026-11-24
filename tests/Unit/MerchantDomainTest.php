<?php

namespace Tests\Unit;

use App\Exceptions\AdminException;
use App\Models\AppDomain;
use App\Models\Merchant;
use App\Services\App\DomainService;
use App\Services\App\MerchantService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MerchantDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32);
            $table->unsignedTinyInteger('type')->default(1);
            $table->string('corporate', 32);
            $table->string('registered_address');
            $table->timestamps();
        });

        foreach ([
            '2025_11_02_122555_add_field_to_merchants_table.php',
            '2026_04_27_175244_add_field_to_merchants_table.php',
            '2026_07_03_000001_add_template_fields_to_merchants_table.php',
            '2026_07_17_000001_add_api_and_image_domains_to_merchants_table.php',
            '2026_08_25_093500_create_app_domain_table.php',
            '2026_09_11_113000_add_icp_fields_to_app_domain_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
    }

    public function test_new_merchant_registers_domain_with_company_subject_and_expiry(): void
    {
        $merchant = app(MerchantService::class)->saveMerchant($this->merchantData([
            'domain' => ' https://Example.COM/ ',
        ]));

        $domain = AppDomain::query()->sole();
        $this->assertSame('example.com', $domain->domain);
        $this->assertSame('测试公司', $domain->subject);
        $this->assertSame('2027-12-31', $domain->expire_at->toDateString());
        $this->assertSame(1, $domain->status);
        $this->assertSame($domain->domain, $merchant->domain);
        $this->assertSame('2027-12-31', $merchant->domain_expired_date);
        $this->assertSame('https://api.example.com', $merchant->api_domain);
        $this->assertSame('https://img.example.com', $merchant->image_domain);
    }

    public function test_new_merchant_reuses_existing_domain_without_overwriting_it(): void
    {
        app(DomainService::class)->saveDomain([
            'domain' => 'example.com', 'subject' => '独立登记主体', 'expire_at' => '2028-01-02',
        ]);
        $merchant = app(MerchantService::class)->saveMerchant($this->merchantData());

        $domain = AppDomain::query()->sole();
        $this->assertSame('独立登记主体', $domain->subject);
        $this->assertSame('2028-01-02', $domain->expire_at->toDateString());
        $this->assertSame('2028-01-02', $merchant->domain_expired_date);
    }

    public function test_edit_uses_selected_domain_expiry_and_preserves_current_disabled_domain(): void
    {
        $service = app(MerchantService::class);
        $merchant = $service->saveMerchant($this->merchantData());
        AppDomain::query()->where('domain', 'example.com')->update(['status' => 0, 'expire_at' => '2028-01-03']);
        $this->assertSame('2028-01-03', $service->updateForm($merchant->id)['domain_expire_at']);
        $service->saveMerchant($this->merchantData(['id' => $merchant->id, 'name' => '改名公司']));
        $this->assertSame('example.com', $merchant->fresh()->domain);

        AppDomain::query()->create(['domain' => 'other.example.com', 'subject' => '另一主体', 'expire_at' => '2029-03-07']);
        $service->saveMerchant($this->merchantData(['id' => $merchant->id, 'domain' => 'other.example.com']));
        $this->assertSame('other.example.com', $merchant->fresh()->domain);
        $this->assertSame('2029-03-07', $merchant->fresh()->domain_expired_date);

        $this->expectException(AdminException::class);
        $service->saveMerchant($this->merchantData(['id' => $merchant->id, 'domain' => 'example.com']));
    }

    public function test_edit_does_not_create_an_unregistered_domain(): void
    {
        $service = app(MerchantService::class);
        $merchant = $service->saveMerchant($this->merchantData());

        $this->expectException(AdminException::class);
        $service->saveMerchant($this->merchantData(['id' => $merchant->id, 'domain' => 'unregistered.example.com']));
    }

    public function test_invalid_expiry_cannot_register_domain(): void
    {
        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('请输入有效的域名到期日期');
        app(MerchantService::class)->saveMerchant($this->merchantData(['domain_expire_at' => '2027-02-30']));
    }

    public function test_merchant_insert_failure_rolls_back_new_domain(): void
    {
        DB::unprepared("CREATE TRIGGER reject_merchant BEFORE INSERT ON merchants BEGIN SELECT RAISE(ABORT, 'merchant insert failed'); END");

        try {
            app(MerchantService::class)->saveMerchant($this->merchantData());
            $this->fail('Expected the merchant insert to fail');
        } catch (QueryException $error) {
            $this->assertStringContainsString('merchant insert failed', $error->getMessage());
        }

        $this->assertSame(0, Merchant::query()->count());
        $this->assertSame(0, AppDomain::query()->count());
    }

    private function merchantData(array $overrides = []): array
    {
        return array_replace([
            'name' => '测试公司',
            'corporate' => '测试法人',
            'domain' => 'example.com',
            'domain_expire_at' => '2027-12-31',
        ], $overrides);
    }
}
