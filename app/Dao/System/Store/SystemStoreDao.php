<?php

namespace App\Dao\System\Store;

use App\Dao\BaseDao;
use App\Models\SystemStore;

/**
 * 门店dao
 * Class SystemStoreDao
 *
 * @package App\Dao\System\Store
 */
class SystemStoreDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemStore::class;
    }

    /**
     * 经纬度排序计算
     *
     * @param string $latitude
     * @param string $longitude
     *
     * @return string
     */
    public function distance(string $latitude, string $longitude)
    {
        return "(round(6367000 * 2 * asin(sqrt(pow(sin(((latitude * pi()) / 180 - ({$latitude} * pi()) / 180) / 2), 2) + cos(({$latitude} * pi()) / 180) * cos((latitude * pi()) / 180) * pow(sin(((longitude * pi()) / 180 - ({$longitude} * pi()) / 180) / 2), 2))))) AS distance";
    }

    /**
     * 获取
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $latitude
     * @param string $longitude
     *
     * @return array
     */
    public function getStoreList(array $where, array $field, int $page = 0, int $limit = 0, string $latitude = '', string $longitude = '')
    {
        return $this->search($where)->when($longitude && $latitude, function ($query) use ($longitude, $latitude) {
            $query->select(['*', $this->distance($latitude, $longitude)])->orderByRaw('distance ASC');
        })->when($page && $limit, function ($query) use ($page, $limit) {
            $query->offset(($page - 1) * $limit)->limit($limit);
        })->select($field)->orderByRaw('id desc')->get()->toArray();
    }

    /**
     * 获取门店不分页
     *
     * @param array $where
     *
     * @return array
     */
    public function getStore(array $where)
    {
        return $this->search($where)->orderByRaw('add_time DESC')->select(['id', 'name'])->get()->toArray();
    }
}
