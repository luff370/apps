<?php

namespace App\Http\Controllers\Admin\App;

use App\Models\AppConfig;
use App\Services\App\AppConfigService;
use App\Http\Controllers\Admin\Controller;

/**
 * AppConfigController
 */
class AppConfigController extends Controller
{
    public function __construct(AppConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter);

        return $this->success($data);
    }

    /**
     * 新增表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function create()
    {
        $data = $this->getMore([['app_id', 0]]);

        return $this->success($this->service->createForm($data));
    }

    /**
     * 保存新建
     */
    public function store()
    {
        $data = $this->getMore([
            ['app_id', ''],
            ['channel', 'all'],
            ['version', 'all'],
            ['name', ''],
            ['key', ''],
            ['value', ''],
            ['remark', ''],
            ['is_enable', '1'],
        ]);

        $this->service->save($data);

        // 清除缓存
        \App\Support\Services\AppConfigService::cacheByAppId($data['app_id']);

        return $this->success(100021);
    }

    /**
     * 编辑表单
     *
     * @throws \App\Exceptions\AdminException
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm($id));
    }

    /**
     * 数据更新
     *
     * @throws \App\Exceptions\AdminException
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['app_id', ''],
            ['channel', 'all'],
            ['version', 'all'],
            ['name', ''],
            ['key', ''],
            ['value', ''],
            ['remark', ''],
            ['is_enable', '1'],
        ]);
        $this->service->update($id, $data);

        // 清除缓存
        \App\Support\Services\AppConfigService::cacheByAppId($data['app_id']);

        return $this->success(100001);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        // 清除缓存
        $info = $this->service->get($id);
        if (!empty($info)) {
            $this->service->delete($id);
            \App\Support\Services\AppConfigService::cacheByAppId($info['app_id']);
        }

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field)
    {
        if (!$id = intval($id)) {
            return $this->fail(100100);
        }
        $this->service->update($id, [$field => $value]);

        // 清除缓存
        $appId = AppConfig::query()->where('id', $id)->value('app_id');
        \App\Support\Services\AppConfigService::cacheByAppId($appId);

        return $this->success(100014);
    }

    public function copy($id)
    {
        $info = $this->service->get($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        AppConfig::query()->create(
            [
                'app_id' => $info['app_id'],
                'channel' => $info['channel'],
                'version' => $info['version'],
                'name' => $info['name'],
                'key' => $info['key'],
                'value' => $info['value'],
                'remark' => $info['remark'],
                'is_enable' => 0
            ]
        );

        return $this->success(100021);
    }
}
