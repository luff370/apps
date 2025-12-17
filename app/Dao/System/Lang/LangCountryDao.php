<?php

namespace App\Dao\System\Lang;

use App\Dao\BaseDao;
use App\Models\LangCountry;

class LangCountryDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return LangCountry::class;
    }
}
