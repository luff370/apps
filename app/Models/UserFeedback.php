<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class UserFeedback
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $content
 * @property string $images
 * @property string $email
 * @property string $phone
 * @property int $status
 * @property string $recover_content
 * @property string $admin_name
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class UserFeedback extends Model
{
    protected $table = 'user_feedback';

    const CREATED_AT = 'create_time';

    const UPDATED_AT = 'update_time';

    protected $casts = [
        'images' => 'array',
        'user_id' => 'int',
        'status' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'user_id',
        'uuid',
        'device',
        'platform',
        'os_version',
        'ip',
        'market_channel',
        'version',
        'type',
        'content',
        'images',
        'email',
        'phone',
        'status',
        'recover_content',
        'admin_name',
        'create_time',
        'update_time',
    ];

    /**
     * 账号反馈与当前设备的匿名反馈；缺少身份时不能匹配其他匿名用户。
     */
    public function scopeForClient(\Illuminate\Database\Eloquent\Builder $query, int $appId, int $userId, string $uuid): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('app_id', $appId)->where(function ($query) use ($userId, $uuid) {
            if ($userId > 0) {
                $query->where('user_id', $userId);
            } else {
                $query->whereRaw('1 = 0');
            }

            if ($uuid !== '') {
                $query->orWhere(function ($query) use ($uuid) {
                    $query->where('user_id', 0)->where('uuid', $uuid);
                });
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'account', 'nickname', 'is_vip', 'overdue_time']);
    }
}
