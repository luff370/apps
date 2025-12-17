<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class AppPayment
 *
 * @property int $id
 * @property string $title
 * @property int $pay_app_id
 * @property string $app_id
 * @property string $mch_id
 * @property string $pay_type
 * @property string $pay_channel
 * @property string $return_url
 * @property string $notify_url
 * @property bool $status
 *
 * @package App\Models
 */
class AppPayment extends Model
{
    protected $table = 'app_payments';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
    ];

    protected $fillable = [
        'pay_app_id',
        'app_id',
        'mch_id',
        'pay_type',
        'pay_channel',
        'return_url',
        'notify_url',
        'status',
    ];

    const PayChannelWechat = 'wechat';
    const PayChannelAlipay = 'alipay';
    const PayChannelApple = 'apple';
    const PayChannelGoogle = 'google';

    public static function payChannelMap()
    {
        return [
            self::PayChannelWechat=>'微信支付',
            self::PayChannelAlipay=>'支付宝',
            self::PayChannelApple=>'苹果支付',
            self::PayChannelGoogle=>'谷歌支付',
        ];
    }

    const PayTypeH5 = 'h5';
    const PayTypeMini = 'mini';
    const PayTypeApp = 'app';

    public static function payTypeMap()
    {
        return [
            self::PayTypeH5 => 'H5支付',
            self::PayTypeMini => '小程序支付',
            self::PayTypeApp => 'App支付',
        ];
    }


    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }

    public function payment()
    {
        return $this->belongsTo(SystemPayment::class,'mch_id','mch_id');
    }
}
