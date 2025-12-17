<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\Other\Express;

/**
 * 物流信息
 * Class ExpressDao
 *
 * @package App\Dao\Other
 */
class ExpressDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return Express::class;
    }

    /**
     * 获取物流列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getExpressList(array $where, string $field, int $page, int $limit)
    {
        return $this->search($where)
            ->selectRaw($field)
            ->orderByRaw('sort DESC,id DESC')
            ->when($page > 0 && $limit > 0, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->get()
            ->toArray();
    }

    /**
     * 指定的条件获取物流信息以数组返回
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getExpress(array $where, string $field, string $key = 'id')
    {
        return $this->search($where)->selectRaw($field)->orderByRaw('id DESC')->pluck($field, $key)->toArray();
    }

    /**
     * 通过code获取一条信息
     *
     * @param string $code
     * @param string $field
     *
     * @return array|\Illuminate\Database\Eloquent\Model|null
     */
    public function getExpressByCode(string $code, string $field = '*')
    {
        return $this->getModel()->newQuery()->select($field)->where('code', $code)->first();
    }
}
