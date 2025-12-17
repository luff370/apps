<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class ArticleCourse
 *
 * @property int $id
 * @property int $nid
 * @property string $title
 * @property string $image
 * @property string $url
 * @property int $duration
 * @property string $source
 * @property string $code
 * @property string $is_enable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class ArticleCourse extends BaseModel
{
	protected $table = 'article_course';

	protected $casts = [
		'nid' => 'int'
	];

	protected $fillable = [
		'nid',
		'title',
		'lesson_number',
		'image',
		'url',
		'duration',
		'source',
		'code',
		'is_enable'
	];
}
