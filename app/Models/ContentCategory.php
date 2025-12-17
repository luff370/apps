<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class ArticleCategory
 *
 * @property int $id
 * @property int $pid
 * @property string $title
 * @property string $intr
 * @property string $image
 * @property int $status
 * @property int $sort
 * @property int $is_del
 * @property string $add_time
 * @property int $hidden
 *
 * @package App\Models
 */
class ContentCategory extends Model
{
	protected $table = 'content_category';

	const UPDATED_AT = 'add_time';

	protected $casts = [
		'pid' => 'int',
		'status' => 'int',
		'sort' => 'int',
		'is_del' => 'int',
		'hidden' => 'int'
	];

	protected $fillable = [
		'app_id',
		'pid',
		'title',
		'column',
		'intro',
		'image',
		'status',
		'sort',
		'is_del',
		'add_time',
		'hidden'
	];
}
