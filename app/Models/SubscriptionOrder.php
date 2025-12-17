<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class SubscriptionOrder
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property int $is_sandbox
 * @property string $original_transaction_id
 * @property string $product_id
 * @property string $pay_type
 * @property string $currency
 * @property float $pay_amount
 * @property string $status
 * @property int $subscribe_success_count
 * @property int $subscribe_fail_count
 * @property string $subscribe_fail_reason
 * @property Carbon $purchase_date
 * @property Carbon $expires_date
 * @property Carbon|null $renewal_date
 * @property Carbon|null $cancellation_date
 * @property Carbon|null $grace_period_expires_date
 * @property bool|null $is_trial_period
 * @property int $auto_renew_status
 * @property string $auto_renew_preference
 * @property string|null $latest_receipt
 * @property string $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class SubscriptionOrder extends BaseModel
{
    protected $table = 'subscription_orders';

    protected $casts = [
        'app_id' => 'int',
        'user_id' => 'int',
        'is_sandbox' => 'int',
        'pay_amount' => 'float',
        'subscribe_success_count' => 'int',
        'subscribe_fail_count' => 'int',
        'purchase_date' => 'datetime',
        'expires_date' => 'datetime',
        'renewal_date' => 'datetime',
        'cancellation_date' => 'datetime',
        'grace_period_expires_date' => 'datetime',
        'is_trial_period' => 'bool',
        'auto_renew_status' => 'int'
    ];

    protected $fillable = [
        'app_id',
        'user_id',
        'is_sandbox',
        'original_transaction_id',
        'product_id',
        'pay_type',
        'currency',
        'pay_amount',
        'status',
        'subscribe_success_count',
        'subscribe_fail_count',
        'subscribe_fail_reason',
        'purchase_date',
        'expires_date',
        'renewal_date',
        'cancellation_date',
        'grace_period_expires_date',
        'is_trial_period',
        'auto_renew_status',
        'auto_renew_preference',
        'latest_receipt',
        'remark'
    ];

    // 订阅状态('active','canceled','failed_to_renew','revoked','trial','expired')
    public static function subscribeStatusMap()
    {
        return [
            'active' => '已订阅',
            'canceled' => '已取消',
            'failed_to_renew' => '续订失败',
            'revoked' => '已撤销',
            'trial' => '试用中',
            'expired' => '已过期',
        ];
    }

    public static function subscribeStatusColorMap()
    {
        return [
            'active' => 'success',
            'canceled' => 'red',
            'failed_to_renew' => 'error',
            'revoked' => 'red',
            'trial' => 'primary',
            'expired' => 'red',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'phone', 'nickname', 'account', 'region']);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MemberProduct::class, 'product_id', 'pay_product_id')->select(['id', 'name', 'pay_product_id', 'price','pay_cycle']);
    }

    public function app(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SystemApp::class)->select(['id', 'name']);
    }
}
