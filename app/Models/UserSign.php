<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserSign
 * 
 * @property int $id
 * @property int $uid
 * @property string $title
 * @property int $number
 * @property int $balance
 * @property int $add_time
 *
 * @package App\Models
 */
class UserSign extends Model
{
	protected $table = 'user_sign';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'number' => 'int',
		'balance' => 'int',
		'add_time' => 'int'
	];

	protected $fillable = [
		'uid',
		'title',
		'number',
		'balance',
		'add_time'
	];
}
