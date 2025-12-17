<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\ShippingTemplatesRegion;

/**
 * 指定邮费
 * Class ShippingTemplatesRegionDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesRegionDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ShippingTemplatesRegion::class;
    }

    /**
     * 获取运费模板列表并按照指定字段进行分组
     *
     * @param array $where
     * @param string $group
     * @param string $field
     * @param string $key
     *
     * @return mixed
     */
    public function getShippingGroupArray(array $where, string $group, string $field)
    {
        return $this->search($where)->groupBy($group)->pluck($field)->toArray();
    }

    /**
     * 获取运费模板列表
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getShippingArray(array $where, string $field, string $key)
    {
        return $this->search($where)->selectRaw($field)->get()->keyBy($key)->toArray();
    }

    /**
     * 根据运费模板id和城市id获得包邮数据列表
     *
     * @param array $tempIds
     * @param array $cityId
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getTempRegionList(array $tempIds, array $cityId, string $field = '*', string $key = '*')
    {
        return $this->getModel()->newQuery()->whereIn('temp_id', $tempIds)->whereIn('city_id', $cityId)->orderByRaw('city_id asc')->pluck($field, $key);
    }
}
