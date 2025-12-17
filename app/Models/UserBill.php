<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserBill
 *
 * @property int $id
 * @property int $uid
 * @property string $link_id
 * @property int $pm
 * @property string $title
 * @property string $category
 * @property string $type
 * @property float $number
 * @property float $balance
 * @property string $mark
 * @property int $add_time
 * @property bool $status
 * @property bool $take
 * @property int $frozen_time
 *
 * @package App\Models
 */
class UserBill extends Model
{
	protected $table = 'user_bill';

	const CREATED_AT = 'add_time';

	protected $casts = [
		'app_id' => 'int',
		'uid' => 'int',
		'pm' => 'int',
		'number' => 'float',
		'balance' => 'float',
	];

	protected $fillable = [
		'app_id',
		'uid',
		'link_id',
		'pm',
		'title',
		'category',
		'type',
		'number',
		'balance',
		'mark',
		'add_time',
		'status',
		'frozen_time'
	];
}
