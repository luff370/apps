<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\MemberShip;

class MemberShipDao extends BaseDao
{
    /** 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        // TODO: Implement setModel() method.
        return MemberShip::class;
    }

    /**后台获取会员卡类型接口
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param array $field
     */
    public function getSearchList(array $where, int $page = 0, int $limit = 0, array $field = ['*'])
    {
        return $this->search($where)->orderByRaw('sort desc,id desc')
            ->select($field)
            ->page($page, $limit)
            ->get();
    }

    /**获取会员类型api接口
     *
     * @return mixed
     */
    public function getApiList(array $where)
    {
        return $this->search()->where($where)->orderByRaw('sort desc')->get()->toArray();
    }
}
