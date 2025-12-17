<?php

namespace App\Dao\System\Config;

use App\Dao\BaseDao;
use App\Models\SystemConfig;

/**
 * 系统配置
 * Class SystemConfigDao
 *
 * @package App\Dao\System\Config
 */
class SystemConfigDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemConfig::class;
    }

    /**
     * 获取某个系统配置
     *
     * @param string $configName
     *
     * @return mixed
     */
    public function getConfigValue(string $configName)
    {
        return $this->search(['menu_name' => $configName])->value('value');
    }

    /**
     * 获取所有配置
     */
    public function getConfigAll(array $configName = []): \Illuminate\Support\Collection
    {
        if ($configName) {
            return $this->search(['menu_name' => $configName])->pluck('value', 'menu_name');
        } else {
            return $this->getModel()->newQuery()->pluck('value', 'menu_name');
        }
    }

    /**
     * 获取配置列表分页
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getConfigList(array $where, int $page, int $limit): array
    {
        return $this->search($where)->offset(($page - 1) * $limit)->limit($limit)->orderByRaw('sort desc,id asc')->get()->toArray();
    }

    /**
     * 获取某些分类配置下的配置列表
     *
     * @param int $tabId
     * @param int $status
     *
     * @return array
     */
    public function getConfigTabAllList(int $tabId, int $status = 1)
    {
        $where['tab_id'] = $tabId;
        // if ($status == 1) {
        //     $where['status'] = $status;
        // }

        return $this->search($where)->orderByRaw('sort desc')->get()->toArray();
    }

    /**
     * 获取上传配置中的上传类型
     *
     * @param string $configName
     */
    public function getUploadTypeList(string $configName): \Illuminate\Support\Collection
    {
        return $this->search(['menu_name' => $configName])->pluck('upload_type', 'type');
    }
}
