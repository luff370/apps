<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

/**
 * Class ArticleAiDialogue
 *
 * @property int $id
 * @property int $nid
 * @property string $prompt
 * @property string $greeting
 * @property string $params
 *
 * @package App\Models
 */
class ArticleAiDialogue extends Model
{
    protected $table = 'article_ai_dialogue';

    public $timestamps = false;

    protected $casts = [
        'nid' => 'int',
        'params' => 'array',
    ];

    protected $fillable = [
        'nid',
        'prompt',
        'greeting',
        'params',
    ];
}
