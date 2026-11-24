<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemAttachmentCategory
 * 
 * @property int $id
 * @property int $pid
 * @property string $name
 * @property string $enname
 *
 * @package App\Models
 */
class SystemAttachmentCategory extends Model
{
    public const PLAN_REMARK_KEY = 'plan_remark';
    public const PLAN_REMARK_NAME = '计划备注';

	protected $table = 'system_attachment_category';
	public $timestamps = false;

	protected $casts = [
		'pid' => 'int'
	];

	protected $fillable = [
		'pid',
		'name',
		'enname'
	];
}
