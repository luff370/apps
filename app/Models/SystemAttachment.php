<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Exceptions\AdminException;
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
        'plan_remark_draft',
    ];

    public static function isPlanRemarkPath(string $path): bool
    {
        return preg_match('~^attach/\d{4}/\d{2}/plan-remark-[a-f0-9]{32}\.(?:png|jpg|gif|webp|bmp)$~D', ltrim($path, '/')) === 1;
    }

    public static function planRemarkDraftKey($token, int $adminId): string
    {
        if ($adminId <= 0 || !is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new AdminException('计划备注图片草稿无效');
        }

        return hash('sha256', $adminId . ':' . $token);
    }

    public static function planRemarkDraftPrefix(string $draftKey): string
    {
        return 'plan-remark-' . substr($draftKey, 0, 16);
    }

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
