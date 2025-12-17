<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Services\Shipping\SystemCityServices;
use App\Services\Shipping\ShippingTemplatesServices;

/**
 * 运费模板
 * Class ShippingTemplates
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class ShippingTemplateController extends Controller
{
    /**
     * 构造方法
     * ShippingTemplates constructor.
     *
     * @param ShippingTemplatesServices $services
     */
    public function __construct(ShippingTemplatesServices $services)
    {
        $this->service = $services;
    }

    /**
     * 运费模板列表
     */
    public function temp_list()
    {
        $where = $this->getMore([
            [['name', 's'], ''],
        ]);

        return $this->success($this->service->getShippingList($where));
    }

    /**
     * 修改
     *
     * @return string
     * @throws \Exception
     */
    public function edit($id)
    {
        return $this->success($this->service->getShipping((int) $id));
    }

    /**
     * 保存或者修改
     *
     * @param int $id
     */
    public function save($id = 0)
    {
        $data = $this->getMore([
            [['region_info', 'a'], []],
            [['appoint_info', 'a'], []],
            [['no_delivery_info', 'a'], []],
            [['sort', 'd'], 0],
            [['type', 'd'], 0],
            [['name', 's'], ''],
            [['appoint', 'd'], 0],
            [['no_delivery', 'd'], 0],
        ]);
        $this->validateWithScene($data, \App\Http\Requests\Setting\ShippingTemplatesValidate::class, 'save');
        $temp['name'] = $data['name'];
        $temp['type'] = $data['type'];
        $temp['appoint'] = $data['appoint'] && $data['appoint_info'] ? 1 : 0;
        $temp['no_delivery'] = $data['no_delivery'] && $data['no_delivery_info'] ? 1 : 0;
        $temp['sort'] = $data['sort'];
        $temp['add_time'] = time();
        $this->service->save((int) $id, $temp, $data);

        return $this->success(100000);
    }

    /**
     * 删除运费模板
     */
    public function delete()
    {
        [$id] = $this->getMore([
            [['id', 'd'], 0],
        ], true);
        if ($id == 1) {
            return $this->fail(400181);
        } else {
            $this->service->detete($id);

            return $this->success(100002);
        }
    }

    /**
     * 城市数据
     */
    public function city_list(SystemCityServices $services)
    {
        return $this->success($services->getShippingCity());
    }
}
