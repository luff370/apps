<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class AppAgreement
 *
 * @property int $id
 * @property int $type
 * @property string $title
 * @property string|null $content
 * @property int $sort
 * @property int $status
 * @property int $add_time
 *
 * @package App\Models
 */
class AppAgreement extends Model
{
    protected $table = 'app_agreements';

    const CREATED_AT = 'add_time';

    protected $casts = [
        'sort' => 'int',
        'status' => 'int',
    ];

    protected $fillable = [
        'type',
        'app_id',
        'platform',
        'version',
        'title',
        'content',
        'remark',
        'sort',
        'status',
        'add_time',
    ];

    public static function typesMap(): array
    {
        return [
            'privacy' => '隐私协议',
            'user' => '用户协议',
            'sign_out' => '注销协议',
            'vip' => '付费会员协议',
            'agent' => '代理协议',
        ];
    }

    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }
}
