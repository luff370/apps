<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\SystemCity;
use App\Models\ShippingTemplatesRegion;

/**
 *
 * Class ShippingTemplatesRegionCityDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesRegionCityDao extends BaseDao
{
    /**
     * 当前表别名
     *
     * @var string
     */
    protected $alias = 'a';

    /**
     * 设置join连表别名
     *
     * @var string
     */
    protected $joinAlis = 'c';

    /**
     * 设置当前模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ShippingTemplatesRegion::class;
    }

    /**
     * 设置连表模型
     *
     * @return string
     */
    protected function setJoinModel(): string
    {
        return SystemCity::class;
    }
}
