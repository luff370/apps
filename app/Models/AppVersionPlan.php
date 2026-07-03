<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AppVersionPlan extends BaseModel
{
    protected $table = 'app_version_plans';

    protected $casts = [
        'app_id' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'title',
        'version',
        'status',
        'owner_name',
        'planned_release_at',
        'remark',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(AppVersionPlanTask::class, 'plan_id')->orderBy('id');
    }
}
