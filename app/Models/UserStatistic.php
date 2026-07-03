<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * 用户日统计。
 *
 * 该表沉淀每日每个 APP 的新增人数和活跃人数，首页、充值统计、营收报表优先读取这里；
 * 当日表没有数据时，统计服务会回退到用户表或访问日志实时统计。
 *
 * @property int $app_id
 * @property int $new_users_count
 * @property int $active_users_count
 * @property Carbon $date
 *
 * @package App\Models
 */
class UserStatistic extends BaseModel
{
	protected $table = 'user_statistics';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'app_id' => 'int',
		'new_users_count' => 'int',
		'active_users_count' => 'int',
		'date' => 'datetime'
	];

	protected $fillable = [
        'app_id',
        'date',
		'new_users_count',
		'active_users_count'
	];
}
