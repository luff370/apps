<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\MemberRight;

class MemberRightDao extends BaseDao
{
    /** 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        // TODO: Implement setModel() method.
        return MemberRight::class;
    }

    /**获取会员权益接口
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
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->get()->toArray();
    }
}
