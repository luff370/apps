<?php

namespace App\Dao\Shipping;

use App\Dao\BaseDao;
use App\Models\ShippingTemplatesFree;

/**
 * Class ShippingTemplatesFreeCityDao
 *
 * @package App\Dao\Shipping
 */
class ShippingTemplatesFreeCityDao extends BaseDao
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
        return ShippingTemplatesFree::class;
    }
}
