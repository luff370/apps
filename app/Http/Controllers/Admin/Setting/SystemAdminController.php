<?php

namespace App\Http\Controllers\Admin\Setting;

use think\facade\{App, Config};
use App\Http\Controllers\Admin\Controller;
use App\Support\Services\CacheService;
use App\Services\System\Admin\SystemAdminServices;

/**
 * Class SystemAdmin
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemAdminController extends Controller
{
    /**
     * SystemAdmin constructor.
     *
     * @param SystemAdminServices $services
     */
    public function __construct(SystemAdminServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示管理员资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['name', '', '', 'account_like'],
            ['roles', ''],
            ['is_del', 1],
            ['status', ''],
        ]);
        $where['level'] = adminInfo()['level'] + 1;

        return $this->success($this->service->getAdminList($where));
    }

    /**
     * 创建表单
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function create()
    {
        return $this->success($this->service->createForm(adminInfo()['level'] + 1));
    }

    /**
     * 保存管理员
     */
    public function store()
    {
        $data = $this->getMore([
            ['account', ''],
            ['conf_pwd', ''],
            ['pwd', ''],
            ['real_name', ''],
            ['roles', 0],
            ['status', 0],
        ]);

        $this->validateWithScene($data, \App\Http\Requests\Setting\SystemAdminValidata::class);

        $data['level'] = adminInfo()['level'] + 1;
        $this->service->create($data);

        return $this->success(100000);
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     */
    public function edit($id)
    {
        if (!$id) {
            return $this->fail(400182);
        }

        return $this->success($this->service->updateForm(adminInfo()['level'] + 1, (int) $id));
    }

    /**
     * 修改管理员信息
     *
     * @param $id
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['account', ''],
            ['conf_pwd', ''],
            ['pwd', ''],
            ['real_name', ''],
            ['roles', 0],
            ['status', 0],
        ]);

        $this->validateWithScene($data, \App\Http\Requests\Setting\SystemAdminValidata::class, 'update');

        if ($this->service->save((int) $id, $data)) {
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 删除管理员
     *
     * @param $id
     */
    public function destroy($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        if ($this->service->update((int) $id, ['is_del' => 1, 'status' => 0])) {
            return $this->success(100002);
        } else {
            return $this->fail(100008);
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
        $this->service->update((int) $id, ['status' => $status]);

        return $this->success(100014);
    }

    /**
     * 获取当前登陆管理员的信息
     */
    public function info()
    {
        return $this->success(adminInfo());
    }

    /**
     * 修改当前登陆admin信息
     */
    public function update_admin()
    {
        $data = $this->getMore([
            ['real_name', ''],
            ['head_pic', ''],
            ['pwd', ''],
            ['new_pwd', ''],
            ['conf_pwd', ''],
        ]);
        if (!preg_match('/^(?![^a-zA-Z]+$)(?!\D+$).{6,}$/', $data['new_pwd'])) {
            return $this->fail(400183);
        }
        if ($this->service->updateAdmin($this->adminId, $data)) {
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 修改当前登陆admin的文件管理密码
     */
    public function set_file_password()
    {
        $data = $this->getMore([
            ['file_pwd', ''],
            ['conf_file_pwd', ''],
        ]);
        if (!preg_match('/^(?![^a-zA-Z]+$)(?!\D+$).{6,}$/', $data['file_pwd'])) {
            return $this->fail(400183);
        }
        if ($this->service->setFilePassword($this->adminId, $data)) {
            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 退出登陆
     */
    public function logout()
    {
        $key = trim(ltrim(request()->header(Config::get('cookie.token_name')), 'Bearer'));
        CacheService::redisHandler()->delete($key);

        return $this->success();
    }
}
