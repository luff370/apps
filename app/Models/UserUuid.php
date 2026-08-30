<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * 客户端 UUID 首次出现记录。
 *
 * 一个 app 下同一个 uuid 只记一行，created_at 作为新增用户日期；
 * market_channel 记录首次请求时的应用市场，供渠道筛选。
 *
 * @property int $id
 * @property int $app_id
 * @property string $uuid
 * @property string $market_channel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserUuid extends BaseModel
{
    protected $table = 'user_uuids';

    protected $fillable = [
        'app_id',
        'uuid',
        'market_channel',
    ];

    protected $casts = [
        'app_id' => 'int',
    ];
}
