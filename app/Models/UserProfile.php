<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class UserProfile
 * 
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property int $app_id
 * @property string $market_channel
 * @property string $version
 * @property string $name
 * @property string $gender
 * @property string $calendar
 * @property Carbon $birth_date
 * @property string $birth_place
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class UserProfile extends BaseModel
{
	protected $table = 'user_profile';

	protected $casts = [
		'user_id' => 'int',
		'app_id' => 'int',
		'birth_date' => 'datetime'
	];

	protected $fillable = [
		'user_id',
		'uuid',
		'app_id',
		'market_channel',
		'version',
		'name',
		'gender',
		'calendar',
		'birth_date',
		'birth_place'
	];
}
