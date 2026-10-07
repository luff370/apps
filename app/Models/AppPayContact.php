<?php

namespace App\Models;

class AppPayContact extends BaseModel
{
    protected $table = 'app_pay_contacts';

    protected $casts = [
        'sort' => 'int',
        'is_enable' => 'int',
    ];

    protected $fillable = [
        'name',
        'image',
        'sort',
        'is_enable',
    ];

    public function apps()
    {
        return $this->belongsToMany(SystemApp::class, 'app_pay_contact_apps', 'contact_id', 'app_id')
            ->withPivot('assign_count');
    }
}
