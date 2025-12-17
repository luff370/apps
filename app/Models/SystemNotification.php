<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemNotification
 * 
 * @property int $id
 * @property string $mark
 * @property string $name
 * @property string $title
 * @property bool $is_system
 * @property bool $is_app
 * @property bool $is_wechat
 * @property bool $is_routine
 * @property bool $is_sms
 * @property bool $is_ent_wechat
 * @property int $is_wechat_group
 * @property int $is_fly_book
 * @property string $system_title
 * @property string $system_text
 * @property int $app_id
 * @property string $sms_id
 * @property string $sms_text
 * @property int $wechat_id
 * @property int $routine_id
 * @property string $ent_wechat_text
 * @property string $variable
 * @property string $url
 * @property bool $type
 * @property int $add_time
 * @property string $fly_book_text
 * @property string $fly_book_url
 * @property string $wechat_group_text
 *
 * @package App\Models
 */
class SystemNotification extends Model
{
	protected $table = 'system_notification';
	public $timestamps = false;

	protected $casts = [
		'is_system' => 'bool',
		'is_app' => 'bool',
		'is_wechat' => 'bool',
		'is_routine' => 'bool',
		'is_sms' => 'bool',
		'is_ent_wechat' => 'bool',
		'is_wechat_group' => 'int',
		'is_fly_book' => 'int',
		'app_id' => 'int',
		'wechat_id' => 'int',
		'routine_id' => 'int',
		'type' => 'bool',
		'add_time' => 'int'
	];

	protected $fillable = [
		'mark',
		'name',
		'title',
		'is_system',
		'is_app',
		'is_wechat',
		'is_routine',
		'is_sms',
		'is_ent_wechat',
		'is_wechat_group',
		'is_fly_book',
		'system_title',
		'system_text',
		'app_id',
		'sms_id',
		'sms_text',
		'wechat_id',
		'routine_id',
		'ent_wechat_text',
		'variable',
		'url',
		'type',
		'add_time',
		'fly_book_text',
		'fly_book_url',
		'wechat_group_text'
	];
}
