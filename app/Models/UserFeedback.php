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

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'account', 'nickname', 'is_vip', 'overdue_time']);
    }
}
