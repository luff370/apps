<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class Merchant
 *
 * @property int $id
 * @property string $name
 * @property int $type
 * @property string $corporate
 * @property string $registered_address
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class Merchant extends Model
{
	protected $table = 'merchants';
	public $timestamps = false;

	protected $casts = [
		'type' => 'int',
		'create_time' => 'int',
		'update_time' => 'int'
	];

	protected $fillable = [
		'name',
		'type',
		'domain',
		'corporate',
		'registered_address',
		'create_time',
		'update_time'
	];
}
