<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemGroup
 * 
 * @property int $id
 * @property int $cate_id
 * @property string $name
 * @property string $info
 * @property string $config_name
 * @property string|null $fields
 *
 * @package App\Models
 */
class SystemGroup extends Model
{
	protected $table = 'system_group';
	public $timestamps = false;

	protected $casts = [
		'cate_id' => 'int'
	];

	protected $fillable = [
		'cate_id',
		'name',
		'info',
		'config_name',
		'fields'
	];
}
