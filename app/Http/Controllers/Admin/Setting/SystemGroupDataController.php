<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Models\SystemGroup;
use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Models\SystemGroupData;
use App\Services\System\Config\SystemGroupServices;
use App\Services\System\Config\SystemGroupDataServices;
use App\Support\Services\GroupDataService;

/**
 * 数据管理
 * Class SystemGroupData
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemGroupDataController extends Controller
{
    /**
     * 构造方法
     * SystemGroupData constructor.
     *
     * @param SystemGroupDataServices $services
     */
    public function __construct(SystemGroupDataServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取数据列表头
     */
    public function header(SystemGroupServices $services)
    {
        [$gid, $config_name] = $this->getMore([
            ['gid', 0],
            ['config_name', ''],
        ], true);
        if (!$gid && !$config_name) {
            return $this->fail(100100);
        }
        if (!$gid) {
            $gid = $services->value(['config_name' => $config_name], 'id');
        }

        return $this->success($services->getGroupDataTabHeader($gid));
    }

    /**
     * 显示资源列表
     */
    public function index(SystemGroupServices $group)
    {
        $where = $this->getMore([
            ['gid', 0],
            ['status', ''],
            ['config_name', ''],
        ]);
        if (!$where['gid'] && !$where['config_name']) {
            return $this->fail(100100);
        }
        if (!$where['gid']) {
            $where['gid'] = $group->value(['config_name' => $where['config_name']], 'id');
        }
        unset($where['config_name']);

        return $this->success($this->service->getGroupDataList($where));
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        $gid = (int)request()->get('gid');
        if ($this->service->isGroupGidSave($gid, 4, 'index_categy_images')) {
            return $this->fail(400298);
        }
        if ($this->service->isGroupGidSave($gid, 7, 'sign_day_num')) {
            return $this->fail(400299);
        }

        return $this->success($this->service->createForm($gid));
    }

    /**
     * 保存新建的资源
     */
    public function store(SystemGroupServices $services)
    {
        $params = request()->post();
        $gid = (int)$params['gid'];
        $group = $services->getOne(['id' => $gid], 'id,config_name,fields');
        if ($group && $group['config_name'] == 'order_details_images') {
            $groupDatas = $this->service->getColumn(['gid' => $gid], 'value', 'id');
            foreach ($groupDatas as $groupData) {
                $groupData = json_decode($groupData, true);
                if (isset($groupData['order_status']['value']) && $groupData['order_status']['value'] == $params['order_status']) {
                    return $this->fail(400188);
                }
            }
        }
        $this->service->checkSeckillTime($services, $gid, $params);
        $this->checkSign($services, $gid, $params);
        $fields = json_decode($group['fields'], true) ?? [];
        $value = [];
        foreach ($params as $key => $param) {
            foreach ($fields as $index => $field) {
                if ($key == $field["title"]) {
                    if ($param == "") {
                        return $this->fail(400297);
                    } else {
                        $value[$key]["type"] = $field["type"];
                        $value[$key]["value"] = $param;
                    }
                }
            }
        }
        $data = [
            "gid" => $params['gid'],
            "add_time" => time(),
            "value" => json_encode($value),
            "sort" => $params["sort"] ?: 0,
            "status" => $params["status"],
        ];
        $this->service->save($data);

        // 清除缓存
        GroupDataService::clearCache($group['config_name']);

        return $this->success(400189);
    }

    /**
     * 显示指定的资源
     *
     * @param int $id
     */
    public function show(int $id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     */
    public function edit($id)
    {
        $gid = (int)request()->get('gid');
        if (!$gid) {
            return $this->fail(100100);
        }

        return $this->success($this->service->updateForm((int)$gid, (int)$id));
    }

    /**
     * 保存更新的资源
     */
    public function update(SystemGroupServices $services, $id)
    {
        $groupData = $this->service->get($id);
        $fields = $services->getValueFields((int)$groupData["gid"]);
        $params = request()->post();
        $this->service->checkSeckillTime($services, $groupData["gid"], $params, $id);
        $this->checkSign($services, $groupData["gid"], $params);
        $value = [];
        foreach ($params as $key => $param) {
            foreach ($fields as $index => $field) {
                if ($key == $field["title"]) {
                    if ($param == '') {
                        return $this->fail(400297);
                    } else {
                        $value[$key]["type"] = $field["type"];
                        $value[$key]["value"] = $param;
                    }
                }
            }
        }
        $data = [
            "value" => json_encode($value),
            "sort" => $params["sort"],
            "status" => $params["status"],
        ];
        $this->service->update($id, $data);

        // 清除缓存
        $groupName = SystemGroup::query()->where('id',$groupData['gid'])->value('config_name');
        GroupDataService::clearCache($groupName);

        return $this->success(100001);
    }

    /**
     * 删除指定资源
     */
    public function destroy(int $id)
    {
        // 清除缓存
        $gid = SystemGroupData::query()->where('id', $id)->value('gid');
        GroupDataService::clearCacheByGroupId($gid);

        if (!$this->service->delete($id)) {
            return $this->fail(100008);
        } else {
            return $this->success(100002);
        }
    }

    /**
     * 修改状态
     *
     * @param $id
     * @param $status
     */
    public function set_status($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        // 清除缓存
        $gid = SystemGroupData::query()->where('id', $id)->value('gid');
        GroupDataService::clearCacheByGroupId($gid);

        return $this->success(100014);
    }

    /**
     * 检查签到配置
     *
     * @param SystemGroupServices $services
     * @param $gid
     * @param $params
     * @param int $id
     */
    public function checkSign(SystemGroupServices $services, $gid, $params, $id = 0)
    {
        $name = $services->value(['id' => $gid], 'config_name');
        if ($name == 'sign_day_num') {
            if (!$params['sign_num']) {
                throw new AdminException(400196);
            }
            if (!preg_match('/^\+?[1-9]\d*$/', $params['sign_num'])) {
                throw new AdminException(400197);
            }
        }
    }

    public function saveAll()
    {
        $params = request()->post();
        if (!isset($params['config_name']) || !isset($params['data'])) {
            return $this->fail(100100);
        }
        $this->service->saveAllData($params['data'], $params['config_name']);

        // 清除缓存
        GroupDataService::clearCache($params['config_name']);

        return $this->success(400295);
    }

}
