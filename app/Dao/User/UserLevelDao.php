<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserLevel;

/**
 *
 * Class UserLevelDao
 *
 * @package App\Dao\User
 */
class UserLevelDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserLevel::class;
    }

    /**
     * 根据uid 获取用户会员等级详细信息
     *
     * @param int $uid
     * @param string $field
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getUserLevel(int $uid, string $field = '*')
    {
        return $this->getModel()->newQuery()->where('uid', $uid)->where('is_del', 0)->where('status', 1)->select($field)->with(['levelInfo'])->orderByRaw('grade desc,add_time desc')->first();
    }

    /**
     * 获取用户等级折扣
     *
     * @param int $uid
     *
     * @return mixed
     */
    public function getDiscount(int $uid)
    {
        $level = $this->getModel()->newQuery()->where(['uid' => $uid, 'is_del' => 0, 'status' => 1])->with([
            'levelInfo' => function ($query) {
                $query->select('id,discount')->bind(['discount_num' => 'discount']);
            },
        ])->orderByRaw('id desc')->first();

        return $level ? $level->toArray()['discount_num'] : null;
    }
}
