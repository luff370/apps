<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class TrafficSign
 * 
 * @property int $id
 * @property int $cate_id
 * @property string $title
 * @property string $url
 * @property int $sort
 *
 * @package App\Models
 */
class TrafficSign extends BaseModel
{
	protected $table = 'traffic_signs';
	public $timestamps = false;

	protected $casts = [
		'cate_id' => 'int',
		'sort' => 'int'
	];

	protected $fillable = [
		'cate_id',
		'title',
		'url',
		'sort'
	];
}
