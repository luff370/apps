<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class UserWhitelist
 *
 * @property int $id
 * @property int $app_id
 * @property string $platform
 * @property string $market_channel
 * @property string $way
 * @property string $content
 * @property int $type
 * @property int $source
 * @property string $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserWhitelist extends BaseModel
{
    protected $table = 'user_whitelist';

    const TYPE_FREE_AD = 1; // 免广告类型白名单
    const TYPE_FREE_MEMBER = 2; // 免费会员类型白名单

    const WAY_DEVICE = 'device';
    const WAY_IP = 'ip';
    const WAY_REGION = 'region';

    protected $casts = [
        'app_id' => 'int',
        'type' => 'int',
        'source' => 'int'
    ];

    protected $fillable = [
        'app_id',
        'platform',
        'market_channel',
        'way',
        'content',
        'source_ip',
        'source_region',
        'source_device',
        'version',
        'type',
        'source',
        'remark'
    ];

    public static function sourceTypeMap()
    {
        return [
            1 => '手动添加',
            2 => 'IP白名单',
            3 => '自动添加',
        ];
    }

    public static function sourceWayMap()
    {
        return [
            self::WAY_DEVICE => '设备白名单',
            self::WAY_IP => 'IP白名单',
            self::WAY_REGION => '区域白名单',
        ];
    }


}
