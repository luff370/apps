<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserCategory
 *
 * @property int $id
 * @property int $pid
 * @property int $owner_id
 * @property string $name
 * @property int $sort
 * @property bool $type
 * @property string|null $other
 * @property int $add_time
 *
 * @package App\Models
 */
class UserCategory extends Model
{
	protected $table = 'user_category';

	const CREATED_AT = 'add_time';

	protected $casts = [
		'pid' => 'int',
		'owner_id' => 'int',
		'sort' => 'int',
		'type' => 'bool',
	];

	protected $fillable = [
		'pid',
		'owner_id',
		'name',
		'sort',
		'type',
		'other',
		'add_time'
	];
}
