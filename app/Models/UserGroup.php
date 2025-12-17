<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserGroup
 * 
 * @property int $id
 * @property string $group_name
 *
 * @package App\Models
 */
class UserGroup extends Model
{
	protected $table = 'user_group';
	public $timestamps = false;

	protected $fillable = [
		'group_name'
	];
}
