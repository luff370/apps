<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemRole
 * 
 * @property int $id
 * @property string $role_name
 * @property string|null $rules
 * @property int $level
 * @property int $status
 *
 * @package App\Models
 */
class SystemRole extends Model
{
	protected $table = 'system_role';
	public $timestamps = false;

	protected $casts = [
		'level' => 'int',
		'status' => 'int'
	];

	protected $fillable = [
		'role_name',
		'rules',
		'level',
		'status'
	];
}
