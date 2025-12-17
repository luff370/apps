<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class SystemConfig
 *
 * @property int $id
 * @property int $app_id
 * @property string $menu_name
 * @property string $type
 * @property string $input_type
 * @property int $config_tab_id
 * @property string $parameter
 * @property int $upload_type
 * @property string $required
 * @property int $width
 * @property int $high
 * @property string $value
 * @property string $info
 * @property string $desc
 * @property int $sort
 * @property int $status
 * @property int $is_app_show
 *
 * @package App\Models
 */
class SystemConfig extends Model
{
    protected $table = 'system_config';

    const cacheKey = 'system_config';

    public $timestamps = false;

    protected $casts = [
        'config_tab_id' => 'int',
        'upload_type' => 'int',
        'width' => 'int',
        'high' => 'int',
        'sort' => 'int',
        'status' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'menu_name',
        'type',
        'input_type',
        'config_tab_id',
        'parameter',
        'upload_type',
        'required',
        'width',
        'high',
        'value',
        'info',
        'desc',
        'sort',
        'status',
        'is_app_show',
    ];

    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }

    /**
     * 菜单名搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchMenuNameAttr(Builder $query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('menu_name', $value);
        } else {
            $query->where('menu_name', $value);
        }
    }

    /**
     * tab id 搜索
     *
     * @param Builder $query
     * @param $value
     */
    public function searchTabIdAttr(Builder $query, $value)
    {
        $query->where('config_tab_id', $value);
    }

    /**
     * 状态搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchStatusAttr(Builder $query, $value)
    {
        $query->where('status', $value ?: 1);
    }


}
