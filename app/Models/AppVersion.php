<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class AppVersion
 *
 * @property int $id
 * @property int $app_id
 * @property string $platform
 * @property string $version
 * @property string|null $info
 * @property string $url
 * @property bool $is_force
 * @property bool $is_new
 * @property bool $audit_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class AppVersion extends BaseModel
{
    protected $table = 'app_versions';

    protected $casts = [
        'app_id' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'platform',
        'version',
        'info',
        'url',
        'is_force',
        'is_new',
        'audit_status',
        'remark',
    ];

    public static function auditStatusMap()
    {
        return [
            0 => '审核中',
            1 => '审核通过',
            -1 => '未通过',
        ];
    }

    public function app()
    {
        return $this->belongsTo(SystemApp::class)->select(['id', 'name']);
    }
}
