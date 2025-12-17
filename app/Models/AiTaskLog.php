<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class AiTaskLog
 *
 * @property int $id
 * @property int $app_id
 * @property int $user_id
 * @property int $source_id
 * @property int $type
 * @property string $input_content
 * @property string $return_content
 * @property string $market_channel
 * @property string $version
 * @property int $mark
 * @property string $remark
 * @property int $status
 * @property int $create_time
 *
 * @package App\Models
 */
class AiTaskLog extends Model
{
	protected $table = 'ai_task_logs';

	const CREATED_AT = 'create_time';

	protected $casts = [
		'app_id' => 'int',
		'user_id' => 'int',
		'source_id' => 'int',
		'type' => 'int',
		'mark' => 'int',
		'status' => 'int',
	];

	protected $fillable = [
		'app_id',
		'user_id',
		'source_id',
		'task_id',
        'dialogue_id',
		'type',
		'input_content',
		'return_content',
		'market_channel',
		'version',
		'mark',
		'remark',
		'status',
		'create_time',
		'prompt_tokens',
		'completion_tokens',
		'total_tokens',
	];
}
