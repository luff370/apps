<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\Config\SystemConfigServices;
use App\Services\System\Config\SystemConfigTabServices;
use Illuminate\Http\JsonResponse;

/**
 * 配置分类
 * Class SystemConfigTab
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemConfigTabController extends Controller
{
    /**
     * g构造方法
     * SystemConfigTab constructor.
     *
     * @param SystemConfigTabServices $services
     */
    public function __construct(SystemConfigTabServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['status', ''],
            ['title', ''],
        ]);

        return $this->success($this->service->getConfigTabList($where));
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        return $this->success($this->service->createForm());
    }

    /**
     * 保存新建的资源
     */
    public function store()
    {
        $data = $this->getMore([
            'eng_title',
            'status',
            'title',
            'icon',
            ['type', 0],
            ['sort', 0],
            ['pid', 0],
            ['app_id', 0],
        ]);

        // 判断是子级分类，取父级菜单类别和所属应用信息
        if (!empty($data['pid'])) {
            $prentInfo = $this->service->getRow($data['pid']);
            if (empty($prentInfo)) {
                return $this->fail('父级分类信息不存在');
            }

            $data['app_id'] = $prentInfo['app_id'];
            $data['type'] = $prentInfo['type'];
        }

        // 判断应用类型所属应用必填，非应用类型应用ID置0
        if ($data['type'] == 1) {
            if (empty($data['app_id'])) {
                return $this->fail('应用配置类型，所属应用必填');
            }
        } else {
            $data['app_id'] = 0;
        }

        $this->service->save($data);

        return $this->success(400292);
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm((int)$id));
    }

    /**
     * 保存更新的资源
     *
     * @param int $id
     */
    public function update($id)
    {
        $data = $this->getMore([
            'title',
            'status',
            'eng_title',
            'icon',
            ['type', 0],
            ['sort', 0],
            ['pid', 0],
            ['app_id', 0],
        ]);

        // 判断是子级分类，取父级菜单类别和所属应用信息
        if (!empty($data['pid'])) {
            $prentInfo = $this->service->getRow($data['pid']);
            if (empty($prentInfo)) {
                return $this->fail('父级分类信息不存在');
            }

            $oldInfo = $this->service->getRow($id);
            if ($oldInfo['app_id'] != $prentInfo['app_id']) {
                return $this->fail('当前分类所属应用和父级分类所属应用不匹配');
            }

            $data['type'] = $prentInfo['type'];
        }

        // 判断应用类型所属应用必填，非应用类型应用ID置0
        if ($data['type'] == 1) {
            if (empty($data['app_id'])) {
                return $this->fail('应用配置类型，所属应用必填');
            }
        } else {
            $data['app_id'] = 0;
        }

        $this->service->update($id, $data);

        return $this->success(100001);
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     */
    public function destroy(SystemConfigServices $services, $id)
    {
        if ($services->count(['tab_id' => $id])) {
            return $this->fail(400293);
        }

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
     * @return JsonResponse
     */
    public function set_status($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        return $this->success(100014);
    }

    /**
     * 同步应用配置
     *
     * @throws AdminException
     */
    public function syncFromOtherAppConfig($fromAppId, $toAppId): \Illuminate\Http\JsonResponse
    {
        $this->service->syncFromOtherAppConfig($fromAppId, $toAppId);

        return $this->success(100001);
    }
}
