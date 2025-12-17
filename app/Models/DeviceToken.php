<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class DeviceToken
 *
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string $u_token
 * @property string $j_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class DeviceToken extends BaseModel
{
	protected $table = 'device_tokens';

	protected $casts = [
		'user_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'uuid',
		'u_token',
		'j_token'
	];
}
