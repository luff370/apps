<?php

namespace App\Models;

class AppNotice extends BaseModel
{
    protected $table = 'app_notices';

    protected $casts = [
        'app_id' => 'int',
        'sort' => 'int',
        'is_enable' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'title',
        'content',
        'sort',
        'is_enable',
    ];
}
