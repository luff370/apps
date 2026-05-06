<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SystemApiInterface extends Model
{
    protected $table = 'system_api_interfaces';

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $casts = [
        'request_params' => 'array',
        'response_params' => 'array',
        'is_enable' => 'int',
    ];

    protected $fillable = [
        'name',
        'module',
        'path',
        'method',
        'request_params',
        'response_params',
        'is_enable',
        'remark',
    ];

    public function searchKeywordAttr(Builder $query, $value): void
    {
        if ($value !== '') {
            $query->where(function ($sub) use ($value) {
                $sub->where('name', 'like', '%' . $value . '%')
                    ->orWhere('path', 'like', '%' . $value . '%')
                    ->orWhere('module', 'like', '%' . $value . '%');
            });
        }
    }
}
