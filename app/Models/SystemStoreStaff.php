<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemStoreStaff
 * 
 * @property int $id
 * @property int $uid
 * @property string $avatar
 * @property int $store_id
 * @property string $staff_name
 * @property string $phone
 * @property int $verify_status
 * @property int $status
 * @property int $add_time
 *
 * @package App\Models
 */
class SystemStoreStaff extends Model
{
	protected $table = 'system_store_staff';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'store_id' => 'int',
		'verify_status' => 'int',
		'status' => 'int',
		'add_time' => 'int'
	];

	protected $fillable = [
		'uid',
		'avatar',
		'store_id',
		'staff_name',
		'phone',
		'verify_status',
		'status',
		'add_time'
	];
}
