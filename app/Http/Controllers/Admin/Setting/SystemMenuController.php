<?php

namespace App\Http\Controllers\Admin\Setting;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\SystemMenuServices;

/**
 * 菜单权限
 * Class SystemMenu
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemMenuController extends Controller
{
    /**
     * SystemMenu constructor.
     *
     * @param SystemMenuServices $services
     */
    public function __construct(SystemMenuServices $services)
    {
        $this->service = $services;
    }

    /**
     * 菜单展示列表
     */
    public function index()
    {
        $where = $this->getMore([
            // ['is_show', ''],
            ['keyword', ''],
        ]);

        return $this->success($this->service->getList($where));
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        return $this->success($this->service->createMenus());
    }

    /**
     * 保存菜单权限
     */
    public function store()
    {
        $data = $this->getMore([
            ['menu_name', ''],
            ['controller', ''],
            ['module', 'admin'],
            ['action', ''],
            ['icon', ''],
            ['params', ''],
            ['path', []],
            ['menu_path', ''],
            ['api_url', ''],
            ['methods', ''],
            ['unique_auth', ''],
            ['header', ''],
            ['is_header', 0],
            ['pid', 0],
            ['sort', 0],
            ['auth_type', 0],
            ['access', 1],
            ['is_show', 0],
            ['is_show_path', 0],
        ]);

        if (!$data['menu_name']) {
            return $this->fail(400198);
        }
        $data['path'] = implode('/', $data['path']);
        if ($this->service->save($data)) {
            return $this->success(100021);
        } else {
            return $this->fail(100022);
        }
    }

    /**
     * 获取一条菜单权限信息
     *
     * @param int $id
     */
    public function show($id)
    {
        if (!$id) {
            return $this->fail(100026);
        }

        return $this->success($this->service->find((int) $id));
    }

    /**
     * 修改菜单权限表单获取
     *
     * @param int $id
     */
    public function edit($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->updateMenus((int) $id));
    }

    /**
     * 修改菜单
     *
     * @param $id
     */
    public function update($id)
    {
        if (!$id || !($menu = $this->service->get($id))) {
            return $this->fail(100026);
        }
        $data = $this->getMore([
            'menu_name',
            'controller',
            ['module', 'admin'],
            'action',
            'params',
            ['icon', ''],
            ['menu_path', ''],
            ['api_url', ''],
            ['methods', ''],
            ['unique_auth', ''],
            ['path', []],
            ['sort', 0],
            ['pid', 0],
            ['is_header', 0],
            ['header', ''],
            ['auth_type', 0],
            ['access', 1],
            ['is_show', 0],
            ['is_show_path', 0],
        ]);
        if (!$data['menu_name']) {
            return $this->fail(400198);
        }
        $data['path'] = implode('/', $data['path']);
        if ($this->service->update($id, $data)) {
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     *
     * @throws \App\Exceptions\AdminException
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        if (!$id) {
            return $this->fail(100100);
        }

        $this->service->delete((int) $id);

        return $this->success(100002);
    }

    /**
     * 显示和隐藏
     *
     * @param $id
     */
    public function setShow($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        [$show, $isShowPath] = $this->getMore([['is_show', 0], ['is_show_path', 0]], true);

        if ($this->service->update($id, ['is_show' => $show, 'is_show_path' => $isShowPath])) {
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 获取菜单数据
     */
    public function menus()
    {
        [$menus, $unique] = $this->service->getMenusList(adminInfo()['roles'], (int) adminInfo()['level']);

        return $this->success(['menus' => $menus, 'unique' => $unique]);
    }

    /**
     * 获取接口列表
     *
     * @return array
     */
    public function ruleList()
    {
        //获取所有的路由
        $ruleList = Route::getRuleList();
        $menuApiList = $this->service->getColumn(['auth_type' => 2, 'is_del' => 0], "concat(`api_url`,'_',lower(`methods`)) as rule");
        if ($menuApiList) {
            $menuApiList = array_column($menuApiList, 'rule');
        }
        $list = [];
        foreach ($ruleList as $item) {
            $item['rule'] = str_replace('adminapi/', '', $item['rule']);
            if (!in_array($item['rule'] . '_' . $item['method'], $menuApiList)) {
                $item['real_name'] = $item['option']['real_name'] ?? '';
                unset($item['option']);
                $item['method'] = strtoupper($item['method']);
                $list[] = $item;
            }
        }

        return $this->success($list);
    }
}
