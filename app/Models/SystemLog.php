<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemLog
 * 
 * @property int $id
 * @property int $admin_id
 * @property string $admin_name
 * @property string $path
 * @property string $page
 * @property string $method
 * @property string $ip
 * @property string $type
 * @property int $add_time
 * @property int $merchant_id
 *
 * @package App\Models
 */
class SystemLog extends Model
{
	protected $table = 'system_log';
	public $timestamps = false;

	protected $casts = [
		'admin_id' => 'int',
		'add_time' => 'int',
		'merchant_id' => 'int'
	];

	protected $fillable = [
		'admin_id',
		'admin_name',
		'path',
		'page',
		'method',
		'ip',
		'type',
		'add_time',
		'merchant_id'
	];
}
