<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserThird
 * 
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $appid
 * @property string $openid
 * @property string $unionid
 * @property string $nickname
 * @property string|null $avatar
 * @property string $status
 * @property int $bind_time
 * @property int $unbind_time
 *
 * @package App\Models
 */
class UserThird extends Model
{
	protected $table = 'user_third';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'bind_time' => 'int',
		'unbind_time' => 'int'
	];

	protected $fillable = [
		'user_id',
		'type',
		'appid',
		'openid',
		'unionid',
		'nickname',
		'avatar',
		'status',
		'bind_time',
		'unbind_time'
	];
}
