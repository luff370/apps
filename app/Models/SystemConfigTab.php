<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class SystemConfigTab
 *
 * @property int $id
 * @property int $app_id
 * @property int $pid
 * @property string $title
 * @property string $eng_title
 * @property int $status
 * @property int $info
 * @property string $icon
 * @property int $type
 * @property int $sort
 *
 * @package App\Models
 */
class SystemConfigTab extends Model
{
    protected $table = 'system_config_tab';

    public $timestamps = false;

    protected $casts = [
        'app_id' => 'int',
        'pid' => 'int',
        'status' => 'int',
        'info' => 'int',
        'type' => 'int',
        'sort' => 'int',
    ];

    protected $fillable = [
        'app_id',
        'pid',
        'title',
        'eng_title',
        'status',
        'info',
        'icon',
        'type',
        'sort',
    ];

    public function app()
    {
        return $this->belongsTo(SystemApp::class);
    }

    /**
     * 状态搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchStatusAttr(Builder $query, $value)
    {
        if ($value != '') {
            $query->where('status', $value);
        }
    }

    /**
     * pid搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchPidAttr(Builder $query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('pid', $value);
        } else {
            $value && $query->where('pid', $value);
        }
    }

    /**
     * 类型搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchTypeAttr(Builder $query, $value)
    {
        $query->where('status', 1);
        if ($value > -1) {
            $query->where(['type' => $value, 'pid' => 0]);
        }
    }

    /**
     * 分类名称搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchTitleAttr(Builder $query, $value)
    {
        if (!empty($value)) {
            $query->where('title', 'like', '%' . $value . '%');
        }
    }
}
