<?php

declare (strict_types = 1);

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserInvoice;

/**
 * Class UserInvoiceDao
 *
 * @package App\Dao\User
 */
class UserInvoiceDao extends BaseDao
{
    protected function setModel(): string
    {
        return UserInvoice::class;
    }

    /**
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getList(array $where, string $field = '*', int $page, int $limit)
    {
        return $this->search($where)->select($field)->page($page, $limit)->orderByRaw('is_default desc,id desc')->get()->toArray();
    }

    /**
     * 设置默认(个人普通|企业普通|企业专用)
     *
     * @param int $uid
     * @param int $id
     * @param $header_type
     * @param $type
     *
     * @return bool
     */
    public function setDefault(int $uid, int $id, $header_type, $type)
    {
        if (false === $this->getModel()->newQuery()->where('uid', $uid)->where('header_type', $header_type)->where('type', $type)->update(['is_default' => 0])) {
            return false;
        }
        if (false === $this->getModel()->newQuery()->where('id', $id)->update(['is_default' => 1])) {
            return false;
        }

        return true;
    }
}
