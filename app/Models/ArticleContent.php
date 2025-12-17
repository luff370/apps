<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class ArticleContent
 *
 * @property int $nid
 * @property string|null $content
 *
 * @package App\Models
 */
class ArticleContent extends Model
{
	protected $table = 'article_content';
	protected $primaryKey = 'nid';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'nid' => 'int'
	];

	protected $fillable = [
        'nid',
		'content'
	];
}
