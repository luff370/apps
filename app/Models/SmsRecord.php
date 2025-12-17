<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SmsRecord
 * 
 * @property int $id
 * @property int $uid
 * @property string $type
 * @property string $phone
 * @property string|null $content
 * @property int $add_time
 * @property string $add_ip
 * @property string $template
 * @property string $resultcode
 * @property string $record_id
 *
 * @package App\Models
 */
class SmsRecord extends BaseModel
{
	protected $table = 'sms_record';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'add_time' => 'int'
	];

	protected $fillable = [
		'uid',
		'type',
		'phone',
		'content',
		'add_time',
		'add_ip',
		'template',
		'resultcode',
		'record_id'
	];
}
