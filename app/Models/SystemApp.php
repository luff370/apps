<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class SystemApp
 *
 * @property int $id
 * @property int $mer_id
 * @property string $name
 * @property string $url
 * @property string $logo
 * @property array|null $wechat
 * @property array|null $integral
 * @property array|null $pages_path
 * @property array|null $conf
 * @property bool $is_enable
 * @property int $is_del
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class SystemApp extends Model
{
    protected $table = 'system_apps';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
        'mer_id' => 'int',
        'markets' => 'json',
        'is_del' => 'int',
    ];

    protected $fillable = [
        'mer_id',
        'name',
        'package_name',
        'logo',
        'platform',
        'markets',
        'is_enable',
        'score_switch',
        'auto_transfer_switch',
        'secret_key',
        'contact_type',
        'contact_email',
        'contact_number',
        'subscribe_switch',
        'push_channel',
        'uPush_app_key',
        'uPush_app_secret',
        'jPush_app_key',
        'jPush_app_secret',
        'ad_switch',
        'topon_app_id',
        'topon_app_key',
        'pangolin_app_id',
        'pangolin_app_key',
        'youlianghui_app_id',
        'youlianghui_app_key',
        'allowlist_switch',
        'allowlist_ad_channel',
        'splash_ad_code',
        'interstitial_ad_code',
        'native_ad_code',
        'banner_ad_code',
        'draw_ad_code',
        'is_del',
        'create_time',
        'update_time',
    ];

    public static function platformsMap(): array
    {
        return [
            'ios' => '苹果',
            'android' => '安卓',
            'hangmen' => '鸿蒙',
        ];
    }

    public static function marketChannelsMap(): array
    {
        return [
            'ios' => '苹果',
            'google' => '谷歌',
            'huawei' => '华为',
            'rongyao' => '荣耀',
            'xiaomi' => '小米',
            'oppo' => 'OPPO',
            'vivo' => 'VIVO',
            'yyb' => '应用宝',
            'baidu' => '百度',
            'pp' => 'pp助手',
            'uc' => 'UC助手',
        ];
    }

    public static function idToNameMap($filter = []): array
    {
        return self::query()
            ->when($filter, function (Builder $query) use ($filter) {
                $query->where($filter);
            })
            ->pluck('name', 'id')
            ->toArray();
    }

    public function merchant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Merchant::class, 'id', 'mer_id');
    }
}
