<?php

namespace App\Models;

class Agent extends BaseModel
{
    protected $table = 'agents';

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
