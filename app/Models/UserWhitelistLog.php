<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class UserWhitelistLog
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property string $uuid
 * @property string $platform
 * @property string $market_channel
 * @property string $version
 * @property string $device
 * @property string $ip
 * @property string $region
 * @property string $source
 * @property string $source_type
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class UserWhitelistLog extends BaseModel
{
    protected $table = 'user_whitelist_log';
    const UPDATED_AT = null;

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int'
    ];

    protected $fillable = [
        'app_id',
        'user_id',
        'uuid',
        'platform',
        'market_channel',
        'version',
        'device',
        'ip',
        'region',
        'source',
        'source_type'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'uuid', 'uuid')->select(['uuid', 'nickname', 'account', 'id']);
    }
}
