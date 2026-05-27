<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * @property int $id
 * @property int $app_id
 * @property string $market_channel
 * @property string $version
 * @property int $user_id
 * @property string $uuid
 * @property int $ad_id
 * @property string $ad_code
 * @property string $ad_type
 * @property string $ad_channel
 * @property string $ad_index
 * @property int $status
 * @property string $error_code
 * @property string $error_msg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdAccessLog extends BaseModel
{
    public const STATUS_SUCCESS = 0;

    public const STATUS_FAIL = -1;

    protected $table = 'ad_access_logs';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'ad_id' => 'int',
        'status' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'market_channel',
        'version',
        'user_id',
        'uuid',
        'ad_id',
        'ad_code',
        'ad_type',
        'ad_channel',
        'ad_index',
        'status',
        'error_code',
        'error_msg',
    ];

    public static function groupFields(): array
    {
        return ['app_id', 'market_channel', 'version', 'ad_type', 'ad_index', 'ad_channel'];
    }
}
