<?php

namespace App\Http\Controllers\Admin\App;

use App\Models\AppAgreement;
use App\Services\App\AgreementService;
use App\Http\Controllers\Admin\Controller;

/**
 * 协议管理
 */
class AgreementsController extends Controller
{
    public function __construct(AgreementService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取协议列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['status', ''],
            ['keyword', ''],
        ]);
        $data = $this->service->getAllByPage($where, ['*'], ['id' => 'desc'], ['app']);

        return $this->success($data);
    }


    /**
     * 保存新建协议
     */
    public function store()
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['title', ''],
            ['type', ''],
            ['version', 'all'],
            ['platform', 'all'],
            ['content', ''],
            ['remark', ''],
            ['status', 0],
        ]);

        $this->service->save($data);

        return $this->success('保存成功');
    }

    public function show($id)
    {
        $data = $this->service->getRow($id);

        return $this->success($data);
    }

    /**
     * 删除协议
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 修改协议状态
     */
    public function setStatus($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        return $this->success(100014);
    }

    public function copy($id)
    {
        $info = $this->service->get($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        AppAgreement::query()->create(
            [
                'app_id' => $info['app_id'],
                'type' => $info['type'],
                'platform' => $info['platform'],
                'version' => $info['version'],
                'title' => $info['title'],
                'content' => $info['content'],
                'remark' => $info['remark'],
                'sort' => $info['sort'],
                'status' => 0,
            ]
        );

        return $this->success(100021);
    }
}
