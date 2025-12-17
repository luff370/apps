<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;



/**
 * Class SystemFile
 * 
 * @property int $id
 * @property string $cthash
 * @property string $filename
 * @property string $atime
 * @property string $mtime
 * @property string $ctime
 *
 * @package App\Models
 */
class SystemFile extends Model
{
	protected $table = 'system_file';
	public $timestamps = false;

	protected $fillable = [
		'cthash',
		'filename',
		'atime',
		'mtime',
		'ctime'
	];
}
