<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class UserStatistic
 *
 * @property int $app_id
 * @property int $new_users_count
 * @property int $active_users_count
 * @property Carbon $date
 *
 * @package App\Models
 */
class UserStatistic extends BaseModel
{
	protected $table = 'user_statistics';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'app_id' => 'int',
		'new_users_count' => 'int',
		'active_users_count' => 'int',
		'date' => 'datetime'
	];

	protected $fillable = [
        'app_id',
        'date',
		'new_users_count',
		'active_users_count'
	];
}
