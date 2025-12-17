<?php

namespace App\Dao\System\Lang;

use App\Dao\BaseDao;
use App\Models\LangCode;

class LangCodeDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return LangCode::class;
    }
}
