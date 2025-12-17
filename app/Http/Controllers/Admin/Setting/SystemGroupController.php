<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Models\SystemGroupData;
use App\Services\System\Config\SystemGroupServices;
use App\Services\System\Config\SystemGroupDataServices;
use App\Support\Services\GroupDataService;

/**
 * 组合数据
 * Class SystemGroup
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemGroupController extends Controller
{
    /**
     * 构造方法
     * SystemGroup constructor.
     *
     * @param SystemGroupServices $services
     */
    public function __construct(SystemGroupServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['title', ''],
        ]);

        return $this->success($this->service->getGroupList($where));
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        //
    }

    /**
     * 保存新建的资源
     */
    public function store()
    {
        $params = $this->getMore([
            ['name', ''],
            ['config_name', ''],
            ['cate_id', 0],
            ['info', ''],
            ['typelist', []],
        ]);

        //数据组名称判断
        if (!$params['name']) {
            return $this->fail(400187);
        }
        if (!$params['config_name']) {
            return $this->fail(400274);
        }
        $data["name"] = $params['name'];
        $data["config_name"] = $params['config_name'];
        $data["info"] = $params['info'];
        $data["cate_id"] = $params['cate_id'];
        //字段信息判断
        if (!count($params['typelist'])) {
            return $this->fail(400294);
        } else {
            $validate = ["name", "type", "title", "description"];
            foreach ($params["typelist"] as $key => $value) {
                foreach ($value as $name => $field) {
                    if (empty($field["value"]) && in_array($name, $validate)) {
                        return $this->fail("字段" . ($key + 1) . "：" . $field["placeholder"] . "不能为空！");
                    } else {
                        $data["fields"][$key][$name] = $field["value"];
                    }
                }
            }
        }
        $data["fields"] = json_encode($data["fields"]);
        $this->service->save($data);

        return $this->success(400295);
    }

    /**
     * 显示指定的资源
     *
     * @param int $id
     */
    public function show($id)
    {
        $info = $this->service->get($id);
        $fields = json_decode($info['fields'], true);
        $type_list = [];
        foreach ($fields as $key => $v) {
            $type_list[$key]['name']['value'] = $v['name'];
            $type_list[$key]['title']['value'] = $v['title'];
            $type_list[$key]['type']['value'] = $v['type'];
            $type_list[$key]['param']['value'] = $v['param'];
        }
        $info['typelist'] = $type_list;
        unset($info['fields']);

        return $this->success(compact('info'));
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     */
    public function edit($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param int $id
     */
    public function update($id)
    {
        $params = $this->getMore([
            ['name', ''],
            ['config_name', ''],
            ['cate_id', 0],
            ['info', ''],
            ['typelist', []],
        ]);

        //数据组名称判断
        if (!$params['name']) {
            return $this->fail(400187);
        }
        if (!$params['config_name']) {
            return $this->fail(400274);
        }
        //判断ID是否存在，存在就是编辑，不存在就是添加
        if (!$id) {
            if ($this->service->count(['config_name' => $params['config_name']])) {
                return $this->fail(400296);
            }
        }
        $data["name"] = $params['name'];
        $data["config_name"] = $params['config_name'];
        $data["info"] = $params['info'];
        $data["cate_id"] = $params['cate_id'];
        //字段信息判断
        if (!count($params['typelist'])) {
            return $this->fail(400294);
        } else {
            $validate = ["name", "type", "title", "description"];
            foreach ($params["typelist"] as $key => $value) {
                foreach ($value as $name => $field) {
                    if (empty($field["value"]) && in_array($name, $validate)) {
                        return $this->fail(400297);
                    } else {
                        $data["fields"][$key][$name] = $field["value"];
                    }
                }
            }
        }
        $data["fields"] = json_encode($data["fields"]);
        $this->service->update($id, $data);

        return $this->success(400295);
    }

    /**
     * 删除指定资源
     */
    public function destroy($id)
    {
        // 清除缓存
        GroupDataService::clearCacheByGroupId($id);

        if (!$this->service->delete($id)) {
            return $this->fail(100008);
        } else {
            SystemGroupData::query()->where("gid", $id)->delete();

            return $this->success(100002);
        }
    }

    /**
     * 获取组合数据
     */
    public function getgroupBy()
    {
        return $this->success($this->service->getGroupList(['cate_id' => 1], ['id', 'name', 'config_name'])['list']);
    }
}
