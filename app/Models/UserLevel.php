<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserLevel
 * 
 * @property int $id
 * @property int $uid
 * @property int $level_id
 * @property int $grade
 * @property int $valid_time
 * @property bool $is_forever
 * @property int $mer_id
 * @property bool $status
 * @property string $mark
 * @property bool $remind
 * @property bool $is_del
 * @property int $add_time
 * @property int $discount
 *
 * @package App\Models
 */
class UserLevel extends Model
{
	protected $table = 'user_level';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'level_id' => 'int',
		'grade' => 'int',
		'valid_time' => 'int',
		'is_forever' => 'bool',
		'mer_id' => 'int',
		'status' => 'bool',
		'remind' => 'bool',
		'is_del' => 'bool',
		'add_time' => 'int',
		'discount' => 'int'
	];

	protected $fillable = [
		'uid',
		'level_id',
		'grade',
		'valid_time',
		'is_forever',
		'mer_id',
		'status',
		'mark',
		'remind',
		'is_del',
		'add_time',
		'discount'
	];
}
