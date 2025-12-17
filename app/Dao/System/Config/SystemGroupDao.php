<?php

namespace App\Dao\System\Config;

use App\Dao\BaseDao;
use App\Models\SystemGroup;

/**
 * Class SystemGroupDao
 *
 * @package App\Dao\System\Config
 */
class SystemGroupDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return SystemGroup::class;
    }

    /**
     * 获取组合数据分页列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     */
    public function getGroupList(array $where, array $field = ['*'], int $page = 0, int $limit = 0)
    {
        return $this->search($where)->select($field)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->get()->toArray();
    }

    /**
     * 根据配置名称获取配置id
     *
     * @param string $configName
     *
     * @return mixed
     */
    public function getConfigNameId(string $configName)
    {
        return $this->search(['config_name'=>$configName])->pluck('id')->first();
    }
}
