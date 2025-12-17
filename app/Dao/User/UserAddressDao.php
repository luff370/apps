<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserAddress;

/**
 * 用户收获地址
 * Class UserAddressDao
 *
 * @package App\Dao\User
 */
class UserAddressDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserAddress::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where, string $field, int $page, int $limit): array
    {
        return $this->search($where)->selectRaw($field)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->orderByRaw('is_default DESC')
            ->get()
            ->toArray();
    }
}
