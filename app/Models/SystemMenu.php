<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * 菜单规则模型
 * Class SystemMenus
 *
 * @package App\Models\System
 */
class SystemMenu extends Model
{
    /**
     * 数据表主键
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 模型名称
     *
     * @var string
     */
    protected $table = 'system_menus';

    protected $fillable = [
        'pid',
        'icon',
        'menu_name',
        'module',
        'controller',
        'action',
        'api_url',
        'methods',
        'params',
        'sort',
        'is_show',
        'is_show_path',
        'path',
        'menu_path',
        'auth_type',
        'header',
        'is_header',
        'unique_auth',
    ];

    /**
     * 参数修改器
     *
     * @param $value
     *
     * @return false|string
     */
    public function setParamsAttr($value)
    {
        $value = $value ? explode('/', $value) : [];
        $params = array_chunk($value, 2);
        $data = [];
        foreach ($params as $param) {
            if (isset($param[0]) && isset($param[1])) {
                $data[$param[0]] = $param[1];
            }
        }

        return json_encode($data);
    }

    /**
     * 参数获取器
     *
     * @param $_value
     *
     * @return mixed
     */
    public function getParamsAttr($_value)
    {
        return json_decode($_value, true);
    }

    /**
     * pid获取器
     *
     * @param $value
     *
     * @return mixed|string
     */
    public function getPidAttr($value)
    {
        return !$value ? '顶级' : $this->where('pid', $value)->value('menu_name');
    }

    /**
     * 默认条件查询器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchDefaultAttr(Builder $query)
    {
        $query->where(['is_show' => 1, 'access' => 1]);
    }

    /**
     * 是否显示搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchIsShowAttr(Builder $query, $value)
    {
        if ($value != '') {
            $query->where('is_show', $value);
        }
    }

    /**
     * 是否删除搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchIsDelAttr(Builder $query, $value)
    {
        $query->where('is_del', $value);
    }

    /**
     * Pid搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchPidAttr(Builder $query, $value)
    {
        $query->where('pid', $value ?? 0);
    }

    /**
     * 规格搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchRuleAttr(Builder $query, $value)
    {
        $query->whereIn('id', $value)->where('is_del', 0)->orWhere('pid', 0);
    }

    /**
     * 搜索菜单
     *
     * @param Builder $query
     * @param $value
     */
    public function searchKeywordAttr(Builder $query, $value)
    {
        if ($value != '') {
            $query->whereLike('menu_name|id|pid', "%$value%");
        }
    }

    /**
     * 方法搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchActionAttr(Builder $query, $value)
    {
        $query->where('action', $value);
    }

    /**
     * 控制器搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchControllerAttr(Builder $query, $value)
    {
        $query->where('controller', lcfirst($value));
    }

    /**
     * 访问地址搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchUrlAttr(Builder $query, $value)
    {
        $query->where('api_url', $value);
    }

    /**
     * 参数搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchParamsAttr(Builder $query, $value)
    {
        $query->where(function ($query) use ($value) {
            $query->where('params', $value)->orWhere('params', "'[]'");
        });
    }

    /**
     * 权限标识搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchUniqueAttr(Builder $query, $value)
    {
        $query->where('is_del', 0);
        if ($value) {
            $query->whereIn('id', $value);
        }
    }

    /**
     * 菜单规格搜索
     *
     * @param Builder $query
     * @param $value
     */
    public function searchRouteAttr(Builder $query, $value)
    {
        $query->where('auth_type', 1)->where('is_show', 1)->where('is_del', 0);
        if ($value) {
            $query->whereIn('id', $value);
        }
    }

    /**
     * Id搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchIdAttr(Builder $query, $value)
    {
        $query->whereIn('id', $value);
    }

    /**
     * is_show_path
     *
     * @param Builder $query
     * @param $value
     */
    public function searchIsShowPathAttr(Builder $query, $value)
    {
        $query->where('is_show_path', $value);
    }

    /**
     * auth_type
     *
     * @param Builder $query
     * @param $value
     */
    public function searchAuthTypeAttr(Builder $query, $value)
    {
        $query->where('auth_type', $value);
    }
}
