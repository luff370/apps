<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemUserLevel
 * 
 * @property int $id
 * @property int $mer_id
 * @property string $name
 * @property float $money
 * @property int $valid_date
 * @property bool $is_forever
 * @property bool $is_pay
 * @property bool $is_show
 * @property int $grade
 * @property float $discount
 * @property string $image
 * @property string $icon
 * @property string|null $explain
 * @property int $add_time
 * @property bool $is_del
 * @property int $exp_num
 *
 * @package App\Models
 */
class SystemUserLevel extends Model
{
	protected $table = 'system_user_level';
	public $timestamps = false;

	protected $casts = [
		'mer_id' => 'int',
		'money' => 'float',
		'valid_date' => 'int',
		'is_forever' => 'bool',
		'is_pay' => 'bool',
		'is_show' => 'bool',
		'grade' => 'int',
		'discount' => 'float',
		'add_time' => 'int',
		'is_del' => 'bool',
		'exp_num' => 'int'
	];

	protected $fillable = [
		'mer_id',
		'name',
		'money',
		'valid_date',
		'is_forever',
		'is_pay',
		'is_show',
		'grade',
		'discount',
		'image',
		'icon',
		'explain',
		'add_time',
		'is_del',
		'exp_num'
	];
}
