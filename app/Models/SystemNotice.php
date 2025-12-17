<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemNotice
 * 
 * @property int $id
 * @property string $title
 * @property string $type
 * @property string $icon
 * @property string $url
 * @property string $content
 * @property string $template
 * @property string $push_admin
 * @property string $wechat_group_name
 * @property int $status
 *
 * @package App\Models
 */
class SystemNotice extends Model
{
	protected $table = 'system_notice';
	public $timestamps = false;

	protected $casts = [
		'status' => 'int'
	];

	protected $fillable = [
		'title',
		'type',
		'icon',
		'url',
		'content',
		'template',
		'push_admin',
		'wechat_group_name',
		'status'
	];
}
