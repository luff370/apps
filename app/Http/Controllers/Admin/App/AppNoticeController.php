<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\AppNoticeService;
use Illuminate\Http\Request;

class AppNoticeController extends Controller
{
    public function __construct(AppNoticeService $service)
    {
        $this->service = $service;
    }

    public function index($appId)
    {
        $where = ['app_id' => (int) $appId];
        $data = $this->service->getAllByPage($where, ['*'], ['sort' => 'desc', 'id' => 'desc']);

        return $this->success($data);
    }

    public function save($appId, Request $request)
    {
        $appId = (int) $appId;
        $id = (int) $request->input('id', 0);
        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        $sort = max(0, (int) $request->input('sort', 0));
        $isEnable = (int) $request->input('is_enable', 1) === 1 ? 1 : 0;

        if ($title === '') {
            return $this->fail('请填写公告标题');
        }
        if ($content === '') {
            return $this->fail('请填写公告内容');
        }
        if (mb_strlen($title) > 100) {
            return $this->fail('公告标题不能超过100个字');
        }
        if (mb_strlen($content) > 5000) {
            return $this->fail('公告内容不能超过5000个字');
        }

        $data = [
            'app_id' => $appId,
            'title' => $title,
            'content' => $content,
            'sort' => $sort,
            'is_enable' => $isEnable,
        ];

        if ($id > 0) {
            $row = $this->service->findByApp($appId, $id);
            if (!$row) {
                return $this->fail('公告不存在');
            }
            $row->fill($data)->save();
        } else {
            $this->service->save($data);
        }

        return $this->success('保存成功');
    }

    public function setSort($appId, $id, Request $request)
    {
        $row = $this->service->findByApp((int) $appId, (int) $id);
        if (!$row) {
            return $this->fail('公告不存在');
        }

        $row->sort = max(0, (int) $request->input('sort', 0));
        $row->save();

        return $this->success('排序已更新');
    }

    public function setStatus($appId, $id, Request $request)
    {
        $row = $this->service->findByApp((int) $appId, (int) $id);
        if (!$row) {
            return $this->fail('公告不存在');
        }

        $row->is_enable = (int) $request->input('is_enable', 0) === 1 ? 1 : 0;
        $row->save();

        return $this->success('状态已更新');
    }

    public function destroy($appId, $id)
    {
        $row = $this->service->findByApp((int) $appId, (int) $id);
        if (!$row) {
            return $this->fail('公告不存在');
        }

        $row->delete();

        return $this->success('删除成功');
    }
}
