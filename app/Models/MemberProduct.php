<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

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
