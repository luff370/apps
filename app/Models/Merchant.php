<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class Merchant
 *
 * @property int $id
 * @property string $name
 * @property int $type
 * @property string $corporate
 * @property string $registered_address
 * @property int $create_time
 * @property int $update_time
 *
 * @package App\Models
 */
class Merchant extends Model
{
	protected $table = 'merchants';
	public $timestamps = false;

	protected $casts = [
		'type' => 'int',
	];

	protected $fillable = [
		'name',
		'type',
		'domain',
		'corporate',
		'registered_address',
	];

    public static function typeNameMap()
    {
        return [
            1 => '有限责任公司',
            2 => '个体工商户',
            3 => '个人',
        ];
    }
}
