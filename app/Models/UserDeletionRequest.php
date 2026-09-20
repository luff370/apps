<?php

namespace App\Models;

/**
 * Class UserDeletionRequest
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property string $identifier
 * @property string $email
 * @property string $reason
 * @property int $status
 * @property string $ip
 * @property string $user_agent
 * @property string $remark
 * @property int|null $processed_at
 * @property int $create_time
 * @property int $update_time
 */
class UserDeletionRequest extends Model
{
    protected $table = 'user_deletion_requests';

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSED = 1;
    public const STATUS_UNMATCHED = 2;

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'status' => 'int',
        'processed_at' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'user_id',
        'identifier',
        'email',
        'reason',
        'status',
        'ip',
        'user_agent',
        'remark',
        'processed_at',
        'create_time',
        'update_time',
    ];

    public static function statusMap(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSED => '已删除',
            self::STATUS_UNMATCHED => '未匹配到账号',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'account', 'nickname', 'email', 'is_del']);
    }

    public function app()
    {
        return $this->belongsTo(SystemApp::class, 'app_id');
    }
}
