<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemCity
 * 
 * @property int $id
 * @property int $city_id
 * @property int $level
 * @property int $parent_id
 * @property string $area_code
 * @property string $name
 * @property string $merger_name
 * @property string $lng
 * @property string $lat
 * @property bool $is_show
 *
 * @package App\Models
 */
class SystemCity extends Model
{
	protected $table = 'system_city';
	public $timestamps = false;

	protected $casts = [
		'city_id' => 'int',
		'level' => 'int',
		'parent_id' => 'int',
		'is_show' => 'bool'
	];

	protected $fillable = [
		'city_id',
		'level',
		'parent_id',
		'area_code',
		'name',
		'merger_name',
		'lng',
		'lat',
		'is_show'
	];
}
