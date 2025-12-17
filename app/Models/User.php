<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class User
 *
 * @property int $id
 * @property string $app_id
 * @property string|null $phone
 * @property string $account
 * @property string $email
 * @property string|null $password
 * @property string $token
 * @property string $uuid
 * @property string $nickname
 * @property string $avatar
 * @property string $gender
 * @property float $balance
 * @property float $total_charge
 * @property int $is_reg
 * @property string $login_ip
 * @property string $login_way
 * @property int $group_id
 * @property int $last_time
 * @property string $last_ip
 * @property bool $is_vip
 * @property int $vip_type
 * @property int $level
 * @property int $overdue_time
 * @property string $channel
 * @property string|null $platform
 * @property string $os_version
 * @property string $package_name
 * @property string $market_channel
 * @property string|null $terminal
 * @property string $status
 * @property bool $is_del
 * @property int|null $reg_time
 * @property int|null $create_time
 * @property int|null $update_time
 *
 * @package App\Models
 */
class User extends Model
{
	protected $table = 'users';

	const CREATED_AT = 'reg_time';
	const UPDATED_AT = 'update_time';

	protected $casts = [
		'balance' => 'float',
		'total_charge' => 'float',
		'is_reg' => 'int',
		'group_id' => 'int',
		'last_time' => 'int',
		'is_vip' => 'bool',
		'vip_type' => 'int',
		'level' => 'int',
		'overdue_time' => 'int',
		'is_del' => 'bool',
		'reg_time' => 'datetime',
	];

	protected $hidden = [
		'password',
		'token'
	];

	protected $fillable = [
		'app_id',
		'phone',
		'account',
		'email',
		'password',
		'token',
		'uuid',
		'nickname',
		'avatar',
		'gender',
		'balance',
		'total_charge',
		'is_reg',
		'login_ip',
		'login_way',
		'group_id',
		'last_time',
		'last_ip',
		'is_vip',
		'vip_type',
		'level',
		'overdue_time',
		'channel',
		'platform',
		'os_version',
		'package_name',
		'market_channel',
		'terminal',
		'device_sn',
		'status',
		'is_del',
		'reg_time',
		'reg_ip',
        'region',
		'update_time'
	];

    public static function vipTypeMap()
    {
        return [
            '0'=>'免费会员',
            '1'=>'付费会员',
            '2'=>'永久会员',
            '3'=>'试用会员',
        ];
    }
}
