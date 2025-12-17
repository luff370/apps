<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class SystemAttachment
 *
 * @property int $att_id
 * @property string $name
 * @property string $att_dir
 * @property string $satt_dir
 * @property string $att_size
 * @property string $att_type
 * @property int $pid
 * @property int $time
 * @property int $image_type
 * @property int $module_type
 * @property string $real_name
 *
 * @package App\Models
 */
class SystemAttachment extends Model
{
    protected $table = 'system_attachment';

    protected $primaryKey = 'att_id';

    public $timestamps = false;

    protected $casts = [
        'pid' => 'int',
        'time' => 'int',
        'image_type' => 'int',
        'module_type' => 'int',
    ];

    protected $fillable = [
        'name',
        'att_dir',
        'satt_dir',
        'att_size',
        'att_type',
        'pid',
        'time',
        'image_type',
        'module_type',
        'real_name',
    ];

    /**
     * 图片类型搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchModuleTypeAttr(Builder $query, $value)
    {
        $query->where('module_type', $value ?: 1);
    }

    /**
     * pid搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchPidAttr(Builder $query, $value)
    {
        if ($value) {
            $query->where('pid', $value);
        }
    }

    /**
     * name模糊搜索
     *
     * @param Builder $query
     * @param $value
     */
    public function searchLikeNameAttr(Builder $query, $value)
    {
        if ($value) {
            $query->where('name', 'LIKE', "$value%");
        }
    }
}
