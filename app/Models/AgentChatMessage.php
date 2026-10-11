<?php

namespace App\Models;

class AgentChatMessage extends BaseModel
{
    protected $table = 'agent_chat_messages';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'corrected',
        'valid',
    ];

    protected $casts = [
        'session_id' => 'int',
        'prompt_tokens' => 'int',
        'completion_tokens' => 'int',
        'total_tokens' => 'int',
        'corrected' => 'bool',
        'valid' => 'bool',
    ];
}
