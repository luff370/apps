<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class UserAmountChangeLog
 *
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property int $type
 * @property float $amount
 * @property float $before_amount
 * @property float $after_amount
 * @property string $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserAmountChangeLog extends BaseModel
{
	protected $table = 'user_amount_change_log';

    const TYPE_RECEIVE_RED_ENVELOPE = 1; // 领取红包
    const TYPE_WITHDRAW = 2; // 提现

	protected $casts = [
		'user_id' => 'int',
		'app_id' => 'int',
		'type' => 'int',
		'amount' => 'float',
		'before_amount' => 'float',
		'after_amount' => 'float'
	];

	protected $fillable = [
		'user_id',
		'app_id',
		'type',
		'amount',
		'before_amount',
		'after_amount',
		'remark'
	];
}
