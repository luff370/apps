<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemStore
 * 
 * @property int $id
 * @property string $name
 * @property string $introduction
 * @property string $phone
 * @property string $address
 * @property string $detailed_address
 * @property string $image
 * @property string $oblong_image
 * @property string $latitude
 * @property string $longitude
 * @property string $valid_time
 * @property string $day_time
 * @property int $add_time
 * @property bool $is_show
 * @property bool $is_del
 *
 * @package App\Models
 */
class SystemStore extends Model
{
	protected $table = 'system_store';
	public $timestamps = false;

	protected $casts = [
		'add_time' => 'int',
		'is_show' => 'bool',
		'is_del' => 'bool'
	];

	protected $fillable = [
		'name',
		'introduction',
		'phone',
		'address',
		'detailed_address',
		'image',
		'oblong_image',
		'latitude',
		'longitude',
		'valid_time',
		'day_time',
		'add_time',
		'is_show',
		'is_del'
	];
}
