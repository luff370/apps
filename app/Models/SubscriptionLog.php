<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class SubscriptionLog
 * 
 * @property int $id
 * @property string $notification_type
 * @property string $notification_uuid
 * @property int $user_id
 * @property string $transaction_id
 * @property string $original_transaction_id
 * @property string $product_id
 * @property Carbon $purchase_date
 * @property Carbon $expires_date
 * @property Carbon|null $renewal_date
 * @property Carbon|null $grace_period_expires_date
 * @property int $quantity
 * @property string $status
 * @property string $sub_type
 * @property int $auto_renew_status
 * @property int $expiration_intent
 * @property string $subscribe_fail_reason
 * @property string $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class SubscriptionLog extends BaseModel
{
	protected $table = 'subscription_logs';

	protected $casts = [
		'user_id' => 'int',
		'purchase_date' => 'datetime',
		'expires_date' => 'datetime',
		'renewal_date' => 'datetime',
		'grace_period_expires_date' => 'datetime',
		'quantity' => 'int',
		'auto_renew_status' => 'int',
		'expiration_intent' => 'int'
	];

	protected $fillable = [
		'notification_type',
		'notification_uuid',
		'user_id',
		'transaction_id',
		'original_transaction_id',
		'product_id',
		'purchase_date',
		'expires_date',
		'renewal_date',
		'grace_period_expires_date',
		'quantity',
		'status',
		'sub_type',
		'auto_renew_status',
		'expiration_intent',
		'subscribe_fail_reason',
		'remark'
	];
}
