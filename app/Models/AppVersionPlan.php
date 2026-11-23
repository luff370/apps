<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppVersionPlan extends BaseModel
{
    protected $table = 'app_version_plans';

    protected $casts = [
        'app_id' => 'int',
        'remark_is_html' => 'boolean',
    ];

    protected $fillable = [
        'app_id',
        'title',
        'version',
        'status',
        'priority',
        'developer_name',
        'operator_name',
        'remark_is_html',
        'remark',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(SystemApp::class, 'app_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AppVersionPlanTask::class, 'plan_id')->orderBy('id');
    }
}
