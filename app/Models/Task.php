<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class Task
 * 
 * @property int $id
 * @property int $app_id
 * @property string $name
 * @property string $type
 * @property string $ad_id
 * @property string $frequency
 * @property int $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Task extends BaseModel
{
	protected $table = 'tasks';

	protected $casts = [
		'app_id' => 'int',
		'count' => 'int'
	];

	protected $fillable = [
		'app_id',
		'name',
		'type',
		'ad_id',
		'frequency',
		'count'
	];
}
