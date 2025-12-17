<?php

namespace App\Http\Controllers\Admin\Cms;

use Illuminate\Http\Request;
use App\Services\Cms\CategoryService;
use App\Http\Controllers\Admin\Controller;

/**
 * 文章分类管理
 */
class CategoryController extends Controller
{
    public function __construct(CategoryService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取分类列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['status', ''],
            ['title', ''],
            ['type', 0],
        ]);
        $type = $where['type'];
        unset($where['type']);
        $data = $this->service->getList($where);
        if ($type == 1) {
            $data = $data['list'];
        }

        return $this->success($data);
    }

    /**
     * 创建新增表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function create(Request $request)
    {
        return $this->success($this->service->createForm(0, $request->get('app_id')));
    }

    /**
     * 保存新建分类
     */
    public function store()
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['title', ''],
            ['pid', 0],
            ['column', ''],
            ['intro', ''],
            ['image', ''],
            ['sort', 0],
            ['status', 0],
        ]);
        if (!$data['title']) {
            return $this->fail(400100);
        }
        $this->service->save($data);

        // CacheService::delete('ARTICLE_CATEGORY');
        return $this->success(100021);
    }

    /**
     * 创建修改表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function edit($id = 0)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->createForm($id));
    }

    /**
     * 保存修改分类
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['title', ''],
            ['pid', 0],
            ['column', ''],
            ['intro', ''],
            ['image', ''],
            ['sort', 0],
            ['status', 0],
        ]);
        $this->service->update($data);

        return $this->success(100001);
    }

    /**
     * 删除文章分类
     *
     * @throws \App\Exceptions\AdminException
     */
    public function destroy($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $this->service->del($id);

        // CacheService::delete('ARTICLE_CATEGORY');
        return $this->success(100002);
    }

    /**
     * 修改文章分类状态
     *
     * @param int $id
     * @param int $status
     *
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set_status($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->setStatus($id, $status);

        // CacheService::delete('ARTICLE_CATEGORY');
        return $this->success(100014);
    }

    /**
     * 获取文章分类
     *
     * @return mixed
     */
    public function categoryList()
    {
        return $this->success($this->service->getArticleTwoCategory());
    }

    /**
     * 树形列表
     */
    public function getTreeList(Request $request)
    {
        $list = $this->service->getTreeList($request->all(['app_id']));

        return $this->success($list);
    }
}
