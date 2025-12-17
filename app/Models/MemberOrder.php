<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class MemberOrder
 *
 * @property int $id
 * @property int $app_id
 * @property string $mch_id
 * @property int $product_id
 * @property int $user_id
 * @property int $type
 * @property string $order_no
 * @property string $member_type
 * @property int $quantity
 * @property string $pay_type
 * @property string $pay_source
 * @property bool $paid
 * @property float $pay_price
 * @property float $member_price
 * @property int $pay_time
 * @property string $trade_no
 * @property int $is_subscribe
 * @property int $is_free
 * @property int $is_permanent
 * @property int $vip_day
 * @property int $add_time
 * @property string $currency
 * @property string $subscribe_product_id
 * @property string $subscribe_status
 * @property int $subscribe_success_count
 * @property int $subscribe_fail_count
 * @property Carbon|null $purchase_date
 * @property Carbon|null $expires_date
 * @property bool $is_trial_period
 * @property string|null $latest_receipt
 * @property bool $is_del
 * @property string $remark
 *
 * @package App\Models
 */
class MemberOrder extends BaseModel
{
    protected $table = 'member_orders';

    const TYPE_SINGLE_PURCHASE = 1;

    const TYPE_SUBSCRIBE = 2;

    const TYPE_FREE = 3;

    const PAY_STATUS_UNPAID = 'unpaid';
    const PAY_STATUS_PAID = 'paid';
    const PAY_STATUS_FIELD = 'payment_failed';

    protected $casts = [
        'app_id' => 'int',
        'product_id' => 'int',
        'user_id' => 'int',
        'type' => 'int',
        'paid' => 'bool',
        'pay_price' => 'float',
        'member_price' => 'float',
        'pay_time' => 'int',
        'is_subscribe' => 'int',
        'is_free' => 'int',
        'is_permanent' => 'int',
        'vip_day' => 'int',
        'purchase_date' => 'datetime',
        'expires_date' => 'datetime',
        'is_trial_period' => 'bool',
    ];

    protected $fillable = [
        'app_id',
        'mch_id',
        'product_id',
        'user_id',
        'type',
        'order_no',
        'member_type',
        'quantity',
        'pay_type',
        'pay_source',
        'paid',
        'pay_price',
        'member_price',
        'pay_time',
        'trade_no',
        'is_subscribe',
        'is_free',
        'is_permanent',
        'vip_day',
        'status',
        'currency',
        'purchase_date',
        'expires_date',
        'is_trial_period',
        'remark',
        'market_channel',
        'version',
    ];

    public static function typeMap()
    {
        return [
            self::TYPE_SINGLE_PURCHASE => '单次购买',
            self::TYPE_SUBSCRIBE => '自动续费',
            self::TYPE_FREE => '免费赠送',
        ];
    }

    public static function payTypeMap()
    {
        return [
            'apple' => '苹果支付',
            'google' => '谷歌支付',
            'wechat' => '微信支付',
            'alipay' => '支付宝',
        ];
    }

    // 会员类型
    public static function memberTypeMap()
    {
        return [
            'day' => '天',
            'week' => '周',
            'month' => '月',
            'quarter' => '季',
            'year' => '年',
        ];
    }

    // 支付状态
    public static function payStatusMap()
    {
        return [
            'unpaid' => '未支付',
            'payment_failed' => '支付失败',
            'paid' => '已支付',
        ];
    }

    // 支付状态
    public static function payStatusColorMap()
    {
        return [
            'unpaid' => 'default',
            'payment_failed' => 'error',
            'paid' => 'success',
        ];
    }

    // 会员状态
    public static function memberStatusMap()
    {
        return [
            'not_ordered' => '未订购',
            'trial' => '试用中',
            'active' => '有效',
            'expired' => '已过期',
        ];
    }

    public static function memberStatusColorMap()
    {
        return [
            'not_ordered' => 'default',
            'active' => 'success',
            'trial' => 'success',
            'expired' => 'red',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'phone', 'nickname', 'account', 'region']);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MemberProduct::class, 'product_id')->select(['id', 'name', 'pay_product_id', 'price']);
    }

    public function app(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SystemApp::class)->select(['id', 'name']);
    }
}
