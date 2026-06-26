<?php

namespace Tests\Unit;

use App\Models\MemberProduct;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MemberProductPlatformTest extends TestCase
{
    public function test_android_market_uses_channel_before_android_default(): void
    {
        $products = new Collection([
            $this->product(1, 'android', 'vip_year', 168, 10),
            $this->product(2, 'huawei', 'vip_year', 128, 5),
            $this->product(3, 'android', 'vip_month', 18, 20),
        ]);

        $list = MemberProduct::filterPreferredPrices($products, 'android', 'huawei');

        $this->assertSame([3, 2], $list->pluck('id')->all());
        $this->assertSame(128, $list->firstWhere('id', 2)['price']);
    }

    public function test_android_market_falls_back_to_android_when_channel_price_missing(): void
    {
        $products = new Collection([
            $this->product(1, 'android', 'vip_year', 168, 10),
            $this->product(2, 'xiaomi', 'vip_month', 16, 20),
        ]);

        $candidates = $products->whereIn('platform', MemberProduct::pricePlatforms('android', 'huawei'));
        $list = MemberProduct::filterPreferredPrices($candidates, 'android', 'huawei');

        $this->assertSame([1], $list->pluck('id')->all());
    }

    public function test_market_channel_without_platform_still_includes_android_default(): void
    {
        $this->assertSame(['all', 'android', 'huawei'], MemberProduct::pricePlatforms('', 'huawei'));
        $this->assertSame(['all', 'ios'], MemberProduct::pricePlatforms('', 'ios'));
    }

    private function product(int $id, string $platform, string $filterCode, int $price, int $sort): array
    {
        return [
            'id' => $id,
            'platform' => $platform,
            'filter_code' => $filterCode,
            'pay_product_id' => '',
            'serial_number' => '',
            'name' => 'product-' . $filterCode,
            'price' => $price,
            'sort' => $sort,
        ];
    }
}
