<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\MemberCardBatch;

class MemberCardBatchDao extends BaseDao
{
    /** 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        // TODO: Implement setModel() method.
        return MemberCardBatch::class;
    }

    /**
     * 获取会员卡批次列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $order
     *
     * @return array
     */
    public function getList(array $where, int $page = 0, int $limit = 0, string $order = '')
    {
        return $this->search($where)
            ->orderByRaw(($order ? $order . ' ,' : '') . 'sort desc,id desc')
            ->page($page, $limit)->get()->toArray();
    }
}
