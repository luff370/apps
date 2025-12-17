<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserMoney
 * 
 * @property int $id
 * @property int $uid
 * @property string $link_id
 * @property string $type
 * @property string $title
 * @property float $number
 * @property float $balance
 * @property int $pm
 * @property string $mark
 * @property bool $status
 * @property int $add_time
 *
 * @package App\Models
 */
class UserMoney extends Model
{
	protected $table = 'user_money';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'number' => 'float',
		'balance' => 'float',
		'pm' => 'int',
		'status' => 'bool',
		'add_time' => 'int'
	];

	protected $fillable = [
		'uid',
		'link_id',
		'type',
		'title',
		'number',
		'balance',
		'pm',
		'mark',
		'status',
		'add_time'
	];
}
