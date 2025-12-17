<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\ShippingTemplatesFree;

/**
 * 包邮
 * Class ShippingTemplatesFreeDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesFreeDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ShippingTemplatesFree::class;
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
     * @return array
     */
    public function getShippingArray(int $tempId, array $freeIdList)
    {
        return $this->getModel()->newQuery()
            ->where('temp_id', $tempId)
            ->whereIn('uniqid', $freeIdList)
            ->get()
            ->keyBy('uniqid')
            ->toArray();
    }

    /**
     * 是否可以满足包邮
     *
     * @param $tempId
     * @param $cityid
     * @param $number
     * @param $price
     *
     * @return int
     */
    public function isFree($tempId, $cityid, $number, $price)
    {
        return $this->getModel()->newQuery()->where('temp_id', $tempId)
            ->where('city_id', $cityid)
            ->where('number', '<=', $number)
            ->where('price', '<=', $price)->count();
    }

    /**
     * 是否包邮模版数据列表
     *
     * @param $tempId
     * @param $cityid
     * @param int $price
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function isFreeList($tempId, $cityid, $price = 0, string $field = '*', string $key = '')
    {
        return $this->getModel()->newQuery()->where('city_id', $cityid)
            ->when($tempId, function ($query) use ($tempId) {
                if (is_array($tempId)) {
                    $query->whereIn('temp_id', $tempId);
                } else {
                    $query->where('temp_id', $tempId);
                }
            })->when($price, function ($query) use ($price) {
                $query->where('price', '<=', $price);
            })->pluck($field, $key)
            ->toArray();
    }
}
