<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class FoodAdditive
 * 
 * @property int $id
 * @property string $name
 * @property string|null $function
 * @property string|null $food_category
 * @property string|null $max_usage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class FoodAdditive extends BaseModel
{
	protected $table = 'food_additives';

	protected $fillable = [
		'name',
		'function',
		'food_category',
		'max_usage'
	];
}
