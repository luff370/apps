<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemStorage
 * 
 * @property int $id
 * @property string $access_key
 * @property bool $type
 * @property string $name
 * @property string $region
 * @property string $acl
 * @property string $domain
 * @property string $cname
 * @property bool $is_ssl
 * @property bool $status
 * @property bool $is_delete
 * @property int $add_time
 * @property int $update_time
 *
 * @package App\Models
 */
class SystemStorage extends Model
{
	protected $table = 'system_storage';
	public $timestamps = false;

	protected $casts = [
		'type' => 'bool',
		'is_ssl' => 'bool',
		'status' => 'bool',
		'is_delete' => 'bool',
		'add_time' => 'int',
		'update_time' => 'int'
	];

	protected $fillable = [
		'access_key',
		'type',
		'name',
		'region',
		'acl',
		'domain',
		'cname',
		'is_ssl',
		'status',
		'is_delete',
		'add_time',
		'update_time'
	];
}
