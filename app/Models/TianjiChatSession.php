<?php

namespace App\Models;

class TianjiChatSession extends BaseModel
{
    protected $table = 'tianji_chat_sessions';

    protected $fillable = [
        'app_id',
        'user_id',
        'target_type',
        'target_key',
        'title',
        'report',
    ];

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'report' => 'array',
    ];
}
