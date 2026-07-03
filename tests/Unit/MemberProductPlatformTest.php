<?php

namespace Tests\Unit;

use App\Models\MemberProduct;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MemberProductPlatformTest extends TestCase
{
    public function test_products_are_grouped_by_pay_product_id_and_market_price_wins(): void
    {
        $products = new Collection([
            $this->product(1, 'android', 'vip_year', 168, 10),
            $this->product(2, 'huawei', 'vip_year', 128, 5),
            $this->product(3, 'android', 'vip_month', 18, 20),
        ]);

        $list = MemberProduct::filterVisibleProducts($products, 'android', 'huawei');

        $this->assertSame([3, 2], $list->pluck('id')->all());
        $this->assertSame(128, $list->firstWhere('pay_product_id', 'vip_year')['price']);
    }

    public function test_android_default_is_kept_when_market_price_missing(): void
    {
        $products = new Collection([
            $this->product(1, 'android', 'vip_year', 168, 10),
            $this->product(2, 'xiaomi', 'vip_year', 158, 20),
        ]);

        $list = MemberProduct::filterVisibleProducts($products, 'android', 'huawei');

        $this->assertSame([1], $list->pluck('id')->all());
    }

    private function product(int $id, string $platform, string $payProductId, int $price, int $sort): array
    {
        return [
            'id' => $id,
            'platform' => $platform,
            'pay_product_id' => $payProductId,
            'price' => $price,
            'sort' => $sort,
        ];
    }
}
