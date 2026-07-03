<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppVersionPlanTask extends BaseModel
{
    protected $table = 'app_version_plan_tasks';

    protected $casts = [
        'plan_id' => 'int',
        'is_force' => 'int',
        'force' => 'array',
    ];

    protected $fillable = [
        'plan_id',
        'market_channel',
        'name',
        'version',
        'owner_name',
        'status',
        'submitted_at',
        'listed_at',
        'remark',
        'is_force',
        'force',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AppVersionPlan::class, 'plan_id');
    }
}
