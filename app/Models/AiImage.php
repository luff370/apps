<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class AiImage
 * 
 * @property int $id
 * @property int $user_id
 * @property int $app_id
 * @property string $platform
 * @property string $type
 * @property string|null $prompt
 * @property array|null $params
 * @property string|null $input_image
 * @property string|null $output_image
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class AiImage extends BaseModel
{
	protected $table = 'ai_images';

	protected $casts = [
		'user_id' => 'int',
		'app_id' => 'int',
		'params' => 'json'
	];

	protected $fillable = [
		'user_id',
		'app_id',
		'platform',
		'type',
		'prompt',
		'params',
		'input_image',
		'output_image'
	];
}
