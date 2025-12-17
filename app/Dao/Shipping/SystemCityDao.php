<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\SystemCity;

/**
 * 城市数据
 * Class SystemCityDao
 *
 * @package App\Dao\Shipping
 */
class SystemCityDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemCity::class;
    }

    /**
     * 获取城市数据列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getCityList(array $where, string $field = '*')
    {
        return $this->search($where)->selectRaw($field)->get()->toArray();
    }

    /**
     * 获取城市数据以数组形式返回
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getCityArray(array $where, string $field, string $key)
    {
        return $this->search($where)->pluck($field, $key)->toArray();
    }

    /**
     * 删除上级城市和当前城市id
     *
     * @param int $cityId
     *
     * @return bool
     * @throws \Exception
     */
    public function deleteCity(int $cityId)
    {
        return $this->getModel()->newQuery()->where('city_id', $cityId)->orWhere('parent_id', $cityId)->delete();
    }

    /**
     * 获取city_id的最大值
     *
     * @return mixed
     */
    public function getCityIdMax()
    {
        return $this->getModel()->newQuery()->max('city_id');
    }

    /**
     * 获取运费模板城市选择
     *
     * @return array
     */
    public function getShippingCity()
    {
        return $this->getModel()->newQuery()
            ->with('children')
            ->where('parent_id', 0)
            ->where('is_show', 1)
            ->orderByRaw('id asc')
            ->get()
            ->toArray();
    }
}
