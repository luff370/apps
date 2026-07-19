<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Support\Collection;

/**
 * Class MemberProduct
 *
 * @property int $id
 * @property int $app_id
 * @property string $name
 * @property string $label
 * @property string $keyword
 * @property float $ot_price
 * @property float $price
 * @property string $validity_type
 * @property string $give_type
 * @property int $validity
 * @property int $give_validity
 * @property string $pay_product_id
 * @property string $filter_code
 * @property string $platform
 * @property string $serial_number
 * @property int $is_subscribe
 * @property string $pay_cycle
 * @property int $pay_cycle_val
 * @property string $grace_period_type
 * @property int $grace_period
 * @property float $renewal_price
 * @property int $is_enable
 * @property string $remark
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class MemberProduct extends Model
{
    protected $table = 'member_products';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
        'app_id' => 'int',
        'ot_price' => 'float',
        'price' => 'float',
        'validity' => 'int',
        'give_validity' => 'int',
        'is_subscribe' => 'int',
        'pay_cycle_val' => 'int',
        'grace_period' => 'int',
        'renewal_price' => 'float',
        'is_enable' => 'int',
        'sort' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'name',
        'label',
        'keyword',
        'ot_price',
        'price',
        'lang',
        'validity_type',
        'give_type',
        'validity',
        'give_validity',
        'pay_product_id',
        'filter_code',
        'platform',
        'serial_number',
        'is_subscribe',
        'pay_cycle',
        'pay_cycle_val',
        'grace_period_type',
        'grace_period',
        'renewal_price',
        'is_enable',
        'remark',
        'buy_info',
        'sort',
        'create_time',
        'update_time',
    ];

    const TimeTypeYear = 'year';
    const TimeTypeMonth = 'month';
    const TimeTypeWeek = 'week';
    const TimeTypeDay = 'day';
    const TimeTypeHour = 'hour';
    const TimeTypeTimes = 'times';

    public static function validityTypesMap()
    {
        return [
            self::TimeTypeYear => '年',
            self::TimeTypeMonth => '月',
            self::TimeTypeDay => '日',
            self::TimeTypeHour => '时',
            self::TimeTypeTimes => '次',
        ];
    }

    public static function payCycleMap()
    {
        return [
            self::TimeTypeMonth => '月',
            self::TimeTypeWeek => '周',
            self::TimeTypeYear => '年',
        ];
    }

    public static function platformsMap(): array
    {
        $marketChannels = SystemApp::marketChannelsMap();

        return [
                'all'=>'全部',
                'android' => '安卓默认',
            ] + $marketChannels;
    }

    public static function platformName(string $platform): string
    {
        return self::platformsMap()[$platform] ?? $platform;
    }

    public static function visibleProducts($appId, ?string $platform, ?string $marketChannel, ?string $language = null): Collection
    {
        // 先取出当前应用下所有可用于定价的平台配置。
        // 不在查询阶段只限制当前渠道，是因为后面还要按 pay_product_id
        // 在“市场专属价 / 系统平台默认价 / all 兜底价”之间做优先级选择。
        $query = self::query()
            ->where('app_id', $appId)
            ->where('is_enable', 1)
            ->whereIn('platform', ['all', $platform, $marketChannel])
            ->orderBy('sort', 'desc');

        if ((string)$appId === '10008' && !empty($language)) {
            $query->where('lang', $language);
        }

        return self::filterVisibleProducts($query->get(), $platform, $marketChannel);
    }

    public static function filterVisibleProducts(Collection $products, ?string $platform, ?string $marketChannel): Collection
    {
        // 同一个 pay_product_id 表示同一个前端商品，只是不同平台/市场有不同价格。
        // 优先级从低到高是：all 兜底价 -> 系统平台默认价(android/ios) -> 当前应用市场价。
        // array_reverse 后再 array_flip，得到的数字越小优先级越高，方便 sortBy 取第一条。
        $priority = array_flip(array_reverse(['all', $platform, $marketChannel,]));

        return $products
            // 按支付产品 ID 分组，同组只返回一个最终展示/下单的价格配置。
            ->groupBy(fn($product) => self::priceGroupKey($product))
            ->map(fn(Collection $group) => $group->sortBy(fn($product) => $priority[$product['platform']] ?? PHP_INT_MAX)->first())
            ->sortByDesc('sort')
            ->values();
    }

    public static function priceGroupKey($product): string
    {
        // pay_product_id 是应用内产品的唯一标识。
        // 兜底使用 id 只是为了兼容历史脏数据，避免空 pay_product_id 被全部归到一组。
        $payProductId = trim((string)($product['pay_product_id'] ?? ''));
        if ($payProductId !== '') {
            return $payProductId;
        }

        return 'id:' . (string)($product['id'] ?? '');
    }

    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }

    public static array $languages = [
        'en' => '英语',
        'zh' => '中文',
        'zh-CN' => '简体中文',
        'zh-TW' => '繁体中文',
        'ja' => '日语',
        'ko' => '韩语',
        'fr' => '法语',
        'de' => '德语',
        'es' => '西班牙语',
        'pt' => '葡萄牙语',
        'ru' => '俄语',
        'ar' => '阿拉伯语',
        'it' => '意大利语',
        'nl' => '荷兰语',
        'sv' => '瑞典语',
        'th' => '泰语',
        'vi' => '越南语',
        'id' => '印尼语',
        'hi' => '印地语',
        'tr' => '土耳其语',
        'fa' => '波斯语',
    ];

}
