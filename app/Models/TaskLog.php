<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class TaskLog
 * 
 * @property int $id
 * @property int $app_id
 * @property int $task_id
 * @property int $link_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class TaskLog extends BaseModel
{
	protected $table = 'task_logs';

	protected $casts = [
		'app_id' => 'int',
		'task_id' => 'int',
		'link_id' => 'int',
		'user_id' => 'int'
	];

	protected $fillable = [
		'app_id',
		'task_id',
		'link_id',
		'user_id'
	];
}
