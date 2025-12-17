<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class UserSearch
 * 
 * @property int $id
 * @property int $uid
 * @property string $keyword
 * @property string $vicword
 * @property int $num
 * @property string|null $result
 * @property bool $is_del
 * @property int $add_time
 *
 * @package App\Models
 */
class UserSearch extends Model
{
	protected $table = 'user_search';
	public $timestamps = false;

	protected $casts = [
		'uid' => 'int',
		'num' => 'int',
		'is_del' => 'bool',
		'add_time' => 'int'
	];

	protected $fillable = [
		'uid',
		'keyword',
		'vicword',
		'num',
		'result',
		'is_del',
		'add_time'
	];
}
