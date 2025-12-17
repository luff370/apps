<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class Favorite
 * 
 * @property int $id
 * @property int $user_id
 * @property int $type
 * @property int $nid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Favorite extends BaseModel
{
	protected $table = 'favorites';

	protected $casts = [
		'user_id' => 'int',
		'type' => 'int',
		'nid' => 'int'
	];

	protected $fillable = [
		'user_id',
		'type',
		'nid'
	];
}
