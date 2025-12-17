<?php

declare (strict_types = 1);

namespace App\Http\Controllers\Admin\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserLabelServices;
use App\Services\User\UserLabelCateServices;
use App\Http\Requests\User\UserLabeCateValidata;

/**
 * Class UserLabelCate
 *
 * @package App\Http\Controllers\Admin\User
 */
class UserLabelCateController extends Controller
{
    /**
     * UserLabelCate constructor.
     *
     * @param UserLabelCateServices $services
     */
    public function __construct(UserLabelCateServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index(Request $request)
    {
        $where = $this->getMore([
            ['name', ''],
        ]);
        $where['type'] = 0;

        return $this->success($this->service->getLabelList($where));
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
    public function save(Request $request)
    {
        $data = $this->getMore([
            ['name', ''],
            ['sort', 0],
        ]);

        $this->validateWithScene($data, UserLabeCateValidata::class);

        if ($this->service->count(['name' => $data['name']])) {
            return $this->fail(400101);
        }
        $data['type'] = 0;
        if ($this->service->save($data)) {
            $this->service->deleteCateCache();

            return $this->success(100000);
        } else {
            return $this->fail(100006);
        }
    }

    /**
     * 显示指定的资源
     *
     * @param int $id
     */
    public function read($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $info = $this->service->get($id);
        if (!$info) {
            return $this->fail(100026);
        }

        return $this->success($info->toArray());
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm((int) $id));
    }

    /**
     * 保存更新的资源
     *
     * @param int $id
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['name', ''],
            ['sort', 0],
        ]);

        $this->validateWithScene($data, UserLabeCateValidata::class);

        if ($this->service->update($id, $data)) {
            $this->service->deleteCateCache();

            return $this->success(100001);
        } else {
            return $this->fail(100007);
        }
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     */
    public function delete($id)
    {
        if (!$id || !($info = $this->service->get($id))) {
            return $this->fail(100026);
        }
        /** @var $labelServices $labelservice */
        $labelServices = app(UserLabelServices::class);
        $count = $labelServices->getCount(['label_cate' => $id]);
        if ($count) {
            return $this->fail(400323);
        }
        if ($info->delete()) {
            $this->service->deleteCateCache();

            return $this->success(100002);
        } else {
            return $this->fail(100008);
        }
    }

    /**
     * 获取用户标签分类全部
     */
    public function getAll()
    {
        return $this->success($this->service->getLabelCate());
    }
}
