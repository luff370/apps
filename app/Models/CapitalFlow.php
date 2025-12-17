<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class CapitalFlow
 * 
 * @property int $id
 * @property string $flow_id
 * @property int $app_id
 * @property int $mer_id
 * @property string $order_id
 * @property string $trade_no
 * @property string $mch_id
 * @property int $uid
 * @property string $nickname
 * @property string $phone
 * @property string $user_name
 * @property float $price
 * @property bool $trading_type
 * @property string $pay_type
 * @property string $mark
 * @property int $add_time
 *
 * @package App\Models
 */
class CapitalFlow extends Model
{
	protected $table = 'capital_flow';
	public $timestamps = false;

	protected $casts = [
		'app_id' => 'int',
		'mer_id' => 'int',
		'uid' => 'int',
		'price' => 'float',
		'trading_type' => 'bool',
		'add_time' => 'int'
	];

	protected $fillable = [
		'flow_id',
		'app_id',
		'mer_id',
		'order_id',
		'trade_no',
		'mch_id',
		'uid',
		'nickname',
		'phone',
		'user_name',
		'price',
		'trading_type',
		'pay_type',
		'mark',
		'add_time'
	];
}
