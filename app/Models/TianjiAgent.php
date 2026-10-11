<?php

namespace App\Models;

class TianjiAgent extends BaseModel
{
    protected $table = 'tianji_agents';

    protected $fillable = [
        'app_id',
        'name',
        'agent_key',
        'system_prompt',
        'knowledge',
        'temperature',
        'status',
        'remark',
        'inputs',
    ];

    protected $casts = [
        'app_id' => 'int',
        'temperature' => 'float',
        'status' => 'int',
        'inputs' => 'array',
    ];
}
