<?php

namespace App\Dao\System\Log;

use App\Dao\BaseDao;
use App\Models\SystemFile;

/**
 * 文件校验模型
 * Class SystemFileDao
 *
 * @package App\Dao\System\Log
 */
class SystemFileDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemFile::class;
    }

    /**
     * 获取全部
     *
     * @return array
     */
    public function getAllFiles()
    {
        return $this->getModel()->newQuery()->orderByRaw('atime desc')->get()->toArray();
    }
}
