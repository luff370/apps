<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class SystemPayment
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $mch_id
 * @property string $api_key
 * @property string $serial_no
 * @property string $private_key
 * @property string|null $public_key
 * @property float $amount
 * @property bool $is_enable
 *
 * @package App\Models
 */
class SystemPayment extends Model
{
    protected $table = 'system_payments';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
        'amount' => 'float',
    ];

    protected $fillable = [
        'type',
        'name',
        'mer_id',
        'mch_id',
        'api_key',
        'serial_no',
        'private_key',
        'public_key',
        'mch_public_cert',
        'mch_root_cert',
        'amount',
        'is_enable',
    ];

    const PayTypeWechat = 'wechat';
    const PayTypeAlipay = 'alipay';
    const PayTypeApple = 'apple';
    const PayTypeGoogle = 'google';

    public static function typesMap()
    {
        return [
            self::PayTypeWechat=>'微信支付',
            self::PayTypeAlipay=>'支付宝',
            self::PayTypeApple=>'苹果支付',
            self::PayTypeGoogle=>'谷歌支付',
        ];
    }
}
