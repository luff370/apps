<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class ArticleAiCreation
 *
 * @property int $id
 * @property int $nid
 * @property string $prompt
 * @property string $copy_writing
 * @property string $params
 * @property int $is_return_limit
 * @property string $return_limit_values
 *
 * @package App\Models
 */
class ArticleAiCreation extends Model
{
    protected $table = 'article_ai_creation';

    public $timestamps = false;

    protected $casts = [
        'nid' => 'int',
        'params' => 'array',
        'is_return_limit' => 'int',
    ];

    protected $fillable = [
        'nid',
        'prompt',
        'copy_writing',
        'params',
        'is_return_limit',
        'return_limit_values',
    ];
}
