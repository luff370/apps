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
class SystemGroupDatum extends BaseModel
{
	protected $table = 'system_group_data';
	public $timestamps = false;

	protected $casts = [
		'gid' => 'int',
		'add_time' => 'int',
		'sort' => 'int',
		'status' => 'bool'
	];

	protected $fillable = [
		'gid',
		'value',
		'add_time',
		'sort',
		'status'
	];
}
