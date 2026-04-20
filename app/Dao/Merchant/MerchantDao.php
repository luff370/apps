<?php

namespace App\Dao\Merchant;

use App\Dao\BaseDao;
use App\Models\Merchant;

class MerchantDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return Merchant::class;
    }

}
