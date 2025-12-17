<?php

namespace App\Http\Controllers\Admin\File;

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\Attachment\SystemAttachmentCategoryServices;

/**
 * 图片分类管理类
 * Class SystemAttachmentCategory
 *
 * @package App\Http\Controllers\file
 */
class SystemAttachmentCategoryController extends Controller
{

    /**
     * @param SystemAttachmentCategoryServices $service
     */
    public function __construct(SystemAttachmentCategoryServices $service)
    {
        $this->service = $service;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['name', ''],
            ['pid', 0],
        ]);
        if ($where['name'] != '') {
            $where['pid'] = '';
        }

        return $this->success($this->service->getAll($where));
    }

    /**
     * 新增表单
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function create(Request $request)
    {
        return $this->success($this->service->createForm($request->get('id', 0)));
    }

    /**
     * 保存新增
     *
     * @throws \App\Exceptions\AdminException
     */
    public function store()
    {
        $data = $this->getMore([
            ['pid', 0],
            ['name', ''],
        ]);
        if (!$data['name']) {
            return $this->fail(400100);
        }
        $this->service->save($data);

        return $this->success(100021);
    }

    /**
     * 编辑表单
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function edit($id)
    {
        return $this->success($this->service->editForm($id));
    }

    /**
     * 保存更新的资源
     *
     * @throws \App\Exceptions\AdminException
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['pid', 0],
            ['name', ''],
        ]);
        if (!$data['name']) {
            return $this->fail(400100);
        }
        $info = $this->service->get($id);
        $count = $this->service->count(['pid' => $id]);
        if ($count && $info['pid'] != $data['pid']) {
            return $this->fail(400105);
        }
        $this->service->update($id, $data);

        return $this->success(100001);
    }

    /**
     * 删除指定资源
     *
     * @throws \App\Exceptions\AdminException
     */
    public function destroy($id)
    {
        $this->service->del($id);

        return $this->success(100002);
    }
}
