<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\SystemMenuServices;
use App\Services\System\Admin\SystemRoleServices;
use App\Services\System\Admin\SystemAdminServices;

/**
 * Class SystemRole
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemRoleController extends Controller
{
    /**
     * SystemRole constructor.
     *
     * @param SystemRoleServices $services
     */
    public function __construct(SystemRoleServices $services)
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
            ['role_name', ''],
        ]);
        $where['level'] = adminInfo()['level'] + 1;

        return $this->success($this->service->getRoleList($where));
    }

    /**
     * 显示创建资源表单页
     *
     * @param SystemMenuServices $services
     */
    public function create(SystemMenuServices $services)
    {
        $menus = $services->getmenus(adminInfo()['level'] == 0 ? [] : adminInfo()['roles']);

        return $this->success(compact('menus'));
    }

    /**
     * 保存新建的资源
     */
    public function save($id)
    {
        $data = $this->getMore([
            'role_name',
            ['status', 0],
            ['checked_menus', []],
        ]);
        $data['rules'] = $data['checked_menus'];
        unset($data['checked_menus']);

        if (!$data['role_name']) {
            return $this->fail(400220);
        }
        if (!is_array($data['rules']) || !count($data['rules'])) {
            return $this->fail(400221);
        }
        $data['rules'] = implode(',', $data['rules']);
        if ($id) {
            if (!$this->service->update($id, $data)) {
                return $this->fail(100007);
            }

            // \Illuminate\Support\Facades\Cache::clear();
            return $this->success(100001);
        } else {
            $data['level'] = adminInfo()['level'] + 1;
            if (!$this->service->save($data)) {
                return $this->fail(400223);
            }

            // \Illuminate\Support\Facades\Cache::clear();
            return $this->success(400222);
        }
    }

    /**
     * 显示编辑资源表单页
     *
     * @param SystemMenuServices $services
     * @param $id
     */
    public function edit(SystemMenuServices $services, $id)
    {
        $role = $this->service->get($id);
        if (!$role) {
            return $this->fail(100100);
        }
        $menus = $services->getMenus(adminInfo()['level'] == 0 ? [] : adminInfo()['roles']);

        return $this->success(['role' => $role->toArray(), 'menus' => $menus]);
    }

    /**
     * 删除指定资源
     *
     * @param SystemAdminServices $adminServices
     * @param $id
     */
    public function delete(SystemAdminServices $adminServices, $id)
    {
        if ($adminServices->checkRoleUse($id)) {
            return $this->fail(400754);
        }
        if (!$this->service->delete($id)) {
            return $this->fail(100008);
        } else {
            // \Illuminate\Support\Facades\Cache::clear();
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
        if (!$id) {
            return $this->fail(100100);
        }
        $role = $this->service->get($id);
        if (!$role) {
            return $this->fail(400199);
        }
        $role->status = $status;
        if ($role->save()) {
            // \Illuminate\Support\Facades\Cache::clear();
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }
}
