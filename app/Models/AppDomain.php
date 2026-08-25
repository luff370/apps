<?php

namespace App\Models;

/**
 * Class AppDomain
 *
 * @property int $id
 * @property string $domain
 * @property string $subject
 * @property string|null $expire_at
 * @property int $status
 * @property int $risk_level
 * @property string $remark
 */
class AppDomain extends BaseModel
{
    protected $table = 'app_domain';

    protected $casts = [
        'status' => 'int',
        'risk_level' => 'int',
        'expire_at' => 'date',
    ];

    protected $fillable = [
        'domain',
        'subject',
        'expire_at',
        'status',
        'risk_level',
        'remark',
    ];

    public const RISK_LOW = 1;
    public const RISK_MEDIUM = 2;
    public const RISK_HIGH = 3;

    public static function riskLevelMap(): array
    {
        return [
            self::RISK_LOW => '低',
            self::RISK_MEDIUM => '中',
            self::RISK_HIGH => '高',
        ];
    }
}
