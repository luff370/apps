<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\ShippingTemplatesNoDelivery;

/**
 * 不送达
 * Class ShippingTemplatesNoDeliveryDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesNoDeliveryDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ShippingTemplatesNoDelivery::class;
    }

    /**
     * 获取运费模板列表并按照指定字段进行分组
     *
     * @param array $where
     * @param string $group
     * @param string $field
     * @param string|null $key
     *
     * @return array
     */
    public function getShippingGroupArray(array $where, string $group, string $field, $key = null): array
    {
        return $this->search($where)->groupBy($group)->pluck($field, $key)->toArray();
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
    public function getShippingArray(array $where, string $field, string $key): array
    {
        return $this->search($where)->selectRaw($field)->get()->keyBy($key)->toArray();
    }

    /**
     * 是否不送达
     *
     * @param $tempId
     * @param $cityid
     *
     * @return int
     */
    public function isNoDelivery($tempId, $cityid)
    {
        if (is_array($tempId)) {
            return $this->getModel()->newQuery()->where('temp_id', 'in', $tempId)->where('city_id', $cityid)->value('temp_id');
        } else {
            return $this->getModel()->newQuery()->where('temp_id', $tempId)->where('city_id', $cityid)->count();
        }
    }
}
