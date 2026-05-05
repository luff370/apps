<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class AppApiInterface extends Model
{
    protected $table = 'app_api_interfaces';

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $casts = [
        'app_id' => 'int',
        'is_enable' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'package_name',
        'name',
        'module',
        'path',
        'method',
        'alias',
        'is_enable',
        'remark',
    ];

    public function searchKeywordAttr(Builder $query, $value): void
    {
        if ($value !== '') {
            $query->where(function ($sub) use ($value) {
                $sub->where('name', 'like', '%' . $value . '%')
                    ->orWhere('path', 'like', '%' . $value . '%')
                    ->orWhere('alias', 'like', '%' . $value . '%');
            });
        }
    }
}

