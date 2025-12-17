<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemNoticeAdmin
 * 
 * @property int $id
 * @property string $notice_type
 * @property int $admin_id
 * @property int $link_id
 * @property string|null $table_data
 * @property int $is_click
 * @property int $is_visit
 * @property int $visit_time
 * @property int $add_time
 *
 * @package App\Models
 */
class SystemNoticeAdmin extends Model
{
	protected $table = 'system_notice_admin';
	public $timestamps = false;

	protected $casts = [
		'admin_id' => 'int',
		'link_id' => 'int',
		'is_click' => 'int',
		'is_visit' => 'int',
		'visit_time' => 'int',
		'add_time' => 'int'
	];

	protected $fillable = [
		'notice_type',
		'admin_id',
		'link_id',
		'table_data',
		'is_click',
		'is_visit',
		'visit_time',
		'add_time'
	];
}
