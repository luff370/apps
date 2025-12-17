<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserLabel
 * 
 * @property int $id
 * @property int $label_cate
 * @property string $label_name
 *
 * @package App\Models
 */
class UserLabel extends Model
{
	protected $table = 'user_label';
	public $timestamps = false;

	protected $casts = [
		'label_cate' => 'int'
	];

	protected $fillable = [
		'label_cate',
		'label_name'
	];
}
