<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemGroupDatum
 *
 * @property int $id
 * @property int $gid
 * @property string|null $value
 * @property int $add_time
 * @property int $sort
 * @property bool $status
 *
 * @package App\Models
 */
class SystemGroupData extends BaseModel
{
	protected $table = 'system_group_data';
    const cacheKey = 'system_group_data';

	public $timestamps = false;

	protected $casts = [
		'gid' => 'int',
		'add_time' => 'int',
		'sort' => 'int',
		// 'value' => 'array'
	];

	protected $fillable = [
		'gid',
		'value',
		'add_time',
		'sort',
		'status'
	];
}
