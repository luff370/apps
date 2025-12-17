<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Class UserAccessLog
 *
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property string $market_channel
 * @property string $version
 * @property string $os
 * @property string $uuid
 * @property string $device
 * @property string $ip
 * @property string $region
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserAccessLog extends BaseModel
{
    protected $table = 'user_access_log';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $casts = [
        'user_id' => 'int',
        'app_id' => 'int',
        'return_data' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'app_id',
        'market_channel',
        'version',
        'os',
        'uuid',
        'device',
        'ip',
        'region',
        'source',
        'return_data',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'uuid', 'uuid')->select(['uuid', 'nickname', 'account', 'id'])->where('uuid', '!=', '');
    }
}
