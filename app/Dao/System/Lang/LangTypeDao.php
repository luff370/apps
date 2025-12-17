<?php

namespace App\Dao\System\Lang;

use App\Dao\BaseDao;
use App\Models\LangType;

class LangTypeDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return LangType::class;
    }
}
