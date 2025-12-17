<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\SystemCity;
use App\Models\ShippingTemplatesNoDelivery;

/**
 * Class ShippingTemplatesFreeCityDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesNoDeliveryCityDao extends BaseDao
{
    /**
     * 主表别名
     *
     * @var string
     */
    protected $alias = 'a';

    /**
     * 附表别名
     *
     * @var string
     */
    protected $joinAlis = 'c';

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
     * 设置join连表模型
     *
     * @return string
     */
    protected function setJoinModel(): string
    {
        return SystemCity::class;
    }
}
