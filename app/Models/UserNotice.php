<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Jobs\UserNoticePush;
use Carbon\Carbon;

/**
 * Class UserNotice
 *
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property int $type
 * @property string $title
 * @property string $content
 * @property int $status
 * @property Carbon $planned_push_time
 * @property Carbon $push_time
 * @property string $msg_id
 * @property string $error_msg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserNotice extends BaseModel
{
    protected $table = 'user_notice';

    const TypeAuditSuccessful = 10001; // 交通违章审核成功
    const TypeAuditFailed = 10002; // 违章审核失败
    const TypeWithdrawalSuccessful = 10003; // 提现成功
    const TypeWithdrawalFailed = 10004; // 提现失败
    const TypeFeedbackReply = 10005; // 意见反馈回复

    protected $casts = [
        'user_id' => 'int',
        'app_id' => 'int',
        'type' => 'int',
        'status' => 'int',
        'planned_push_time' => 'datetime',
        'push_time' => 'datetime'
    ];

    protected $fillable = [
        'user_id',
        'app_id',
        'type',
        'title',
        'content',
        'status',
        'planned_push_time',
        'push_time',
        'msg_id',
        'error_msg'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

}
