<?php

declare (strict_types = 1);

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\ShippingTemplates;

/**
 *
 * Class ShippingTemplatesDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ShippingTemplates::class;
    }

    /**
     * 获取选择模板列表
     *
     * @return array
     */
    public function getSelectList()
    {
        return $this->search()->orderByRaw('sort DESC,id DESC')->selectRaw('id,name')->get()->toArray();
    }

    /**
     * 获取
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    public function getShippingList(array $where, int $page, int $limit)
    {
        return $this->search($where)
            ->orderByRaw('sort DESC, id DESC')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 插入数据返回主键id
     *
     * @param array $data
     *
     * @return int|string
     */
    public function insertGetId(array $data)
    {
        return $this->getModel()->newQuery()->insertGetId($data);
    }

    /**
     * 获取运费模板指定条件下的数据
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getShippingColumn(array $where, string $field, string $key)
    {
        return $this->search($where)->pluck($field, $key);
    }
}
