<?php

namespace App\Models;

class AgentChatSession extends BaseModel
{
    protected $table = 'agent_chat_sessions';

    protected $fillable = [
        'app_id',
        'user_id',
        'agent_key',
        'title',
        'report',
        'inputs',
    ];

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'report' => 'array',
        'inputs' => 'array',
    ];
}
