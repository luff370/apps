<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Support\Services\{CacheService};
use App\Http\Controllers\Admin\Controller;
use App\Services\Shipping\SystemCityServices;

/**
 * 城市数据
 * Class SystemCity
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemCityController extends Controller
{
    /**
     * 构造方法
     * SystemCity constructor.
     *
     * @param SystemCityServices $services
     */
    public function __construct(SystemCityServices $services)
    {
        $this->service = $services;
    }

    /**
     * 城市列表
     *
     * @return string
     * @throws \Exception
     */
    public function index()
    {
        $where = $this->getMore([
            [['parent_id', 'd'], 0],
        ]);

        return $this->success($this->service->getCityList($where));
    }

    /**
     * 添加城市
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function add()
    {
        [$parentId] = $this->getMore([
            [['parent_id', 'd'], 0],
        ], true);

        return $this->success($this->service->createCityForm($parentId));
    }

    /**
     * 保存
     */
    public function store()
    {
        $data = $this->getMore([
            [['id', 'd'], 0],
            [['name', 's'], ''],
            [['merger_name', 's'], ''],
            [['area_code', 's'], ''],
            [['lng', 's'], ''],
            [['lat', 's'], ''],
            [['level', 'd'], 0],
            [['parent_id', 'd'], 0],
        ]);
        $this->validateWithScene($data, \App\Http\Requests\Setting\SystemCityValidate::class, 'save');
        if ($data['parent_id'] == 0) {
            $data['merger_name'] = $data['name'];
        } else {
            $data['merger_name'] = $this->service->value(['id' => $data['parent_id']], 'name') . ',' . $data['name'];
        }
        if ($data['id'] == 0) {
            unset($data['id']);
            $data['level'] = $data['level'] + 1;
            $data['city_id'] = intval($this->service->getCityIdMax() + 1);
            $this->service->save($data);

            return $this->success(100000);
        } else {
            unset($data['level']);
            unset($data['parent_id']);
            $this->service->update($data['id'], $data);

            return $this->success(100001);
        }
    }

    /**
     * 修改城市
     *
     * @return string
     */
    public function edit($id)
    {
        return $this->success($this->service->updateCityForm($id));
    }

    /**
     * 删除城市
     *
     * @throws \Exception
     */
    public function delete()
    {
        [$id] = $this->getMore([
            [['city_id', 'd'], 0],
        ], true);
        $this->service->deleteCity($id);

        return $this->success(100002);
    }

    /**
     * 清除城市缓存
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clean_cache()
    {
        $res = CacheService::delete('CITY_LIST');
        if ($res) {
            return $this->success(400185);
        } else {
            return $this->fail(400186);
        }
    }
}
