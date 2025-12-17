<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class AppConfigService
 *
 * @property int $id
 * @property int $app_id
 * @property string $channel
 * @property string $version
 * @property string $name
 * @property string $key
 * @property string $value
 * @property string $remark
 * @property int $is_enable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class AppConfig extends BaseModel
{
    const CACHE_BY_APPID = "app_config:%s";

	protected $table = 'app_config';

	protected $casts = [
		'app_id' => 'int',
		'is_enable' => 'int'
	];

	protected $fillable = [
		'app_id',
		'channel',
		'version',
		'name',
		'key',
		'value',
		'remark',
		'is_enable'
	];
}
