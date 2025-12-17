<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * 管理员模型
 * Class SystemAdmin
 *
 * @property int $id
 * @property string $account
 * @property string $head_pic
 * @property string $pwd
 * @property string $real_name
 * @property int $account_type
 * @property string $roles
 * @property string $last_ip
 * @property int $last_time
 * @property int $login_count
 * @property int $level
 * @property int $status
 * @property int $add_time
 * @property int $is_del
 *
 * @package App\Models\System
 */
class SystemAdmin extends Model
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
    protected $table = 'system_admin';

    const CREATED_AT = 'add_time';

    protected $fillable = [
        'account',
        'head_pic',
        'pwd',
        'real_name',
        'account_type',
        'roles',
        'last_ip',
        'last_time',
        'login_count',
        'level',
        'status',
        'add_time',
        'is_del',
    ];

    const AccountTypeManager = 0;

    const AccountTypeService = 1;

    const AccountTypeOption = 2;

    const AccountTypeSupplier = 3;

    const AccountTypeAgent = 4;

    /**
     * 权限数据
     *
     * @param $value
     *
     * @return false|string[]
     */
    public function getRolesAttribute($value)
    {
        return explode(',', $value);
    }


    /**
     * 管理员级别搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchLevelAttr(Builder $query, $value)
    {
        if (is_array($value)) {
            $query->where('level', $value[0], $value[1]);
        } else {
            $query->where('level', $value);
        }
    }

    /**
     * 管理员账号和姓名搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchAccountLikeAttr(Builder $query, $value)
    {
        if ($value) {
            $query->where('account|real_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 管理员账号搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchAccountAttr(Builder $query, $value)
    {
        if ($value) {
            $query->where('account', $value);
        }
    }

    /**
     * 管理员权限搜索器
     *
     * @param Builder $query
     * @param $roles
     */
    public function searchRolesAttr(Builder $query, $roles)
    {
        if ($roles) {
            $query->whereRaw("CONCAT(',',roles,',')  LIKE '%,$roles,%'");
        }
    }

    /**
     * 是否删除搜索器
     *
     * @param Builder $query
     */
    public function searchIsDelAttr(Builder $query)
    {
        $query->where('is_del', 0);
    }

    /**
     * 状态搜索器
     *
     * @param Builder $query
     * @param $value
     */
    public function searchStatusAttr(Builder $query, $value)
    {
        if ($value != '' && $value != null) {
            $query->where('status', $value);
        }
    }
}
