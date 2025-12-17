<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Services\Cms\ContentService;
use App\Http\Controllers\Admin\Controller;

/**
 * 文章管理
 */
class ContentController extends Controller
{
    public function __construct(ContentService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取列表
     *
     * @return mixed
     */
    public function index()
    {
        $where = $this->getMore([
            ['title', ''],
            ['cate_id'],
            ['app_id'],
        ]);
        $data = $this->service->getList($where);

        return $this->success($data);
    }

    /**
     * 保存文章数据
     */
    public function store()
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['cate_id', 0],
            ['title', ''],
            ['sub_title', ''],
            ['image', ''],
            ['label', ''],
            ['remark', ''],
            ['keyword', ''],
            ['code', ''],
            ['images', []],
            ['score', 0],
            ['views', 0],
            ['url', ''],
            ['type', 0],
            ['is_recommend', 0],
            ['is_hot', 0],
            ['status', 1],
            ['content', ''],
            ['prompt', ''],
            ['greeting', ''],
            ['copy_writing', ''],
            ['params', []],
            ['params2', []],
            ['collections', 0],
            ['duration', 0],
            ['source', ''],
            ['is_return_limit', ''],
            ['return_limit_values', ''],
        ]);
        $this->service->save($data);

        return $this->success('保存成功');
    }

    /**
     * 获取单个文章数据
     *
     * @param int $id
     *
     * @return mixed
     */
    public function show($id = 0)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $info = $this->service->detail($id);

        return $this->success($info);
    }

    public function setFieldValue($id, $field, $value)
    {
        $this->service->update($id, [$field => $value]);

        return $this->success('设置成功');
    }

    /**
     * 删除文章
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 保存文章数据
     */
    public function generate(): \Illuminate\Http\JsonResponse
    {
        $data = $this->getMore([
            ['cate_id', 0],
            ['app_id', 0],
            ['article_id', 0],
            ['source', ''],
            ['type', ''],
            ['code', ''],
            ['sort', 0],
            ['is_enable', 0],
        ]);
        $this->service->generate($data);

        return $this->success('创建成功');
    }

}
