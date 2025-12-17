<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserGroupServices;

/**
 * 会员设置
 * Class UserLevel
 *
 * @package app\admin\controller\user
 */
class UserGroupController extends Controller
{
    /**
     * user constructor.
     *
     * @param UserGroupServices $services
     */
    public function __construct(UserGroupServices $services)
    {
        $this->service = $services;
    }

    /**
     * 分组列表
     */
    public function index()
    {
        return $this->success($this->service->getGroupList('*', true));
    }

    /**
     * 添加/修改分组页面
     *
     * @param int $id
     *
     * @return string
     */
    public function add()
    {
        $data = $this->getMore([
            ['id', 0],
        ]);

        return $this->success($this->service->add((int) $data['id']));
    }

    /**
     * @param int $id
     */
    public function save()
    {
        $data = $this->getMore([
            ['id', 0],
            ['group_name', ''],
        ]);
        if (!$data['group_name']) {
            return $this->fail(400321);
        }
        $this->service->save((int) $data['id'], $data);

        return $this->success(100017);
    }

    /**
     * 删除
     *
     * @param $id
     *
     * @throws \Exception
     */
    public function delete()
    {
        $data = $this->getMore([
            ['id', 0],
        ]);
        if (!$data['id']) {
            return $this->fail(100100);
        }

        return $this->success($this->service->delgroupBy((int) $data['id']));
    }
}
