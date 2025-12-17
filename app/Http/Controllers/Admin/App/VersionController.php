<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Models\AppVersion;
use App\Services\System\AppVersionServices;

/**
 *
 * Class AppVersion
 *
 * @package App\Http\Controllers\Admin\System
 */
class VersionController extends Controller
{
    /**
     * user constructor.
     *
     * @param AppVersionServices $services
     */
    public function __construct(AppVersionServices $services)
    {
        $this->service = $services;
    }

    /**
     * 版本列表
     */
    public function index()
    {
        $args = $this->getMore([
            ['app_id', 0],
            ['audit_status', ''],
            ['page', 1],
            ['limit', 15],
        ]);

        return $this->success($this->service->getAllByPage($args, ['*'], ['id' => 'desc'], ['app']));
    }

    /**
     * 新增版本表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function form($id): \Illuminate\Http\JsonResponse
    {
        return $this->success($this->service->createForm($id));
    }

    /**
     * 保存数据
     *
     * @throws \App\Exceptions\AdminException
     */
    public function save()
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['version', ''],
            ['platform', ''],
            ['info', ''],
            ['is_force', 1],
            ['url', ''],
            ['remark', ''],
            ['audit_status', 0],
            ['is_new', 1],
        ]);
        $this->service->versionSave($data['id'], $data);

        return $this->success(100021);
    }

    public function destory($id)
    {
        $this->service->delete($id);

        return $this->success('删除成功');
    }

    public function copy($id)
    {
        $info = $this->service->get($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        AppVersion::query()->create(
            [
                'app_id' => $info['app_id'],
                'platform' => $info['platform'],
                'version' => $info['version'],
                'info' => $info['info'],
                'url' => $info['url'],
                'is_force' => $info['is_force'],
                'is_new' => $info['is_new'],
                'audit_status' => $info['audit_status'],
                'remark' => $info['remark'],
            ]
        );

        return $this->success(100021);
    }
}
