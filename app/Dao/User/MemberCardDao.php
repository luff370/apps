<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\MemberCard;

class MemberCardDao extends BaseDao
{
    /** 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        // TODO: Implement setModel() method.
        return MemberCard::class;
    }

    public function getSearchList(array $where, int $page = 0, int $limit = 0, array $field = ['*'])
    {
        return $this->search($where)->orderByRaw('use_time desc,id desc')
            ->select($field)
            ->when($page > 0 || $limit > 0, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->get();
    }

    /**获取当条会员卡信息
     *
     * @param array $where
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getOneByWhere(array $where)
    {
        return $this->getModel()->newQuery()->where($where)->first();
    }
}
