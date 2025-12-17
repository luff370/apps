<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class AppAdvertisement
 *
 * @property int $id
 * @property int $app_id
 * @property string $title
 * @property string $market_channel
 * @property string $position
 * @property int $type
 * @property int $status
 * @property array $channels
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class AppAdvertisement extends Model
{
    const CACHE_BY_APPID = "advertisements:app_id:%d";

    protected $table = 'app_advertisement';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
        'app_id' => 'int',
        'type' => 'int',
        'status' => 'int',
        'channels' => 'json',
    ];

    protected $fillable = [
        'app_id',
        'title',
        'market_channel',
        'position',
        'type',
        'status',
        'channels',
        'create_time',
        'update_time',
    ];

    const TypeOpenApp = 1;

    const TypeInsertScreen = 2;

    const TypeInfoFlow = 3;

    const TypeDrawFlow = 4;

    const TypeIncentiveVideo = 5;

    const TypeBanner = 6;

    public static function typesMap()
    {
        return [
            self::TypeOpenApp => '开屏广告',
            self::TypeInsertScreen => '插屏广告',
            self::TypeInfoFlow => '信息流广告',
            self::TypeDrawFlow => 'draw信息流',
            self::TypeIncentiveVideo => '插屏广告',
            self::TypeBanner => 'banner广告',
        ];
    }

    public static function adChannelsMap()
    {
        return [
            'topon' => 'topon',
            'pangolin' => '穿山甲',
            'youlianghui' => '优量汇',
            'admob' => 'Admob',
            'meta' => 'Meta',
            'pangle' => 'Pangle',
        ];
    }

    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }
}
