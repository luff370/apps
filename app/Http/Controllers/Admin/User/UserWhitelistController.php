<?php

namespace App\Http\Controllers\Admin\User;

use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Models\UserWhitelist;
use App\Services\User\UserWhitelistService;
use Illuminate\Http\Request;

/**
 * UserWhitelistController
 */
class UserWhitelistController extends Controller
{
    public function __construct(UserWhitelistService $service)
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
            ['type', ''],
            ['way', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter);

        return $this->success($data);
    }

    /**
     * 导入表单
     * @throws AdminException
     */
    public function importForm(Request $request)
    {
        return $this->success($this->service->importForm($request->get('way')));
    }

    /**
     * 导入操作
     */
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $this->getMore([
            ['way', ''],
            ['file', ''],
            ['type', []],
            ['remark', ''],
        ]);
        $data['type'] = convertToPermissionValue($data['type']);
        $this->service->import($data);

        switch ($data['way']) {
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100021);
    }


    /**
     * 新增表单
     * @throws AdminException
     */
    public function create(Request $request)
    {
        return $this->success($this->service->createForm($request->get('way')));
    }

    /**
     * 保存新建
     */
    public function store(): \Illuminate\Http\JsonResponse
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['market_channel', 'all'],
            ['platform', 'all'],
            ['way', ''],
            ['content', ''],
            ['type', []],
            ['remark', ''],
        ]);
        $data['type'] = convertToPermissionValue($data['type']);
        $this->service->save($data);

        switch ($data['way']) {
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100021);
    }


    /**
     * 编辑表单
     * @throws AdminException
     */
    public function edit(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        return $this->success($this->service->updateForm($id));
    }

    /**
     * 数据更新
     */
    public function update($id): \Illuminate\Http\JsonResponse
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['market_channel', 'all'],
            ['platform', 'all'],
            ['way', ''],
            ['content', ''],
            ['type', []],
            ['remark', ''],
        ]);
        $data['type'] = convertToPermissionValue($data['type']);
        $this->service->update($id, $data);

        switch ($data['way']) {
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100001);
    }

    /**
     * 用户白名单添加表单
     */
    public function userForm($userId)
    {
        return $this->success($this->service->userForm($userId));
    }

    /**
     * 用户白名单数据保存
     */
    public function addUser()
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['market_channel', ''],
            ['reg_ip', ''],
            ['last_ip', ''],
            ['device', ''],
            ['version', ''],
            ['type', '0'],
            ['remark', ''],
        ]);

        // 设备白名单保存
        if (!empty($data['device'])) {
            UserWhitelistService::createByDevice($data['device'], $data['type'], $data['remark'], $data['app_id'], $data['market_channel'], $data['version']);
        }
        // IP白名单保存
        if (!empty($data['reg_ip'])) {
            UserWhitelistService::createByIp($data['reg_ip'], $data['type'], $data['remark'], $data['app_id'], $data['market_channel'], $data['version']);
        }
        if (!empty($data['last_ip']) && $data['last_ip'] != $data['reg_ip']) {
            UserWhitelistService::createByIp($data['last_ip'], $data['type'], $data['remark'], $data['app_id'], $data['market_channel'], $data['version']);
        }

        return $this->success(100021);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        $info = $this->service->getRow($id);
        if (empty($info)) {
            return $this->success(100002);
        }

        $way = $info['way'];
        $info->delete($id);

        // 更新缓存
        switch ($way) {
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100002);
    }

    /**
     * 删除数据
     */
    public function batchDel(Request $request): \Illuminate\Http\JsonResponse
    {
        $way = $request->get("way");
        $ids = $request->get("ids");
        if (empty($ids)) {
            return $this->fail("请选择要删除的数据");
        }

        UserWhitelist::query()->whereIn("id", $ids)->delete();

        // 更新缓存
        switch ($way) {
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field)
    {
        $data = $this->service->getRow($id);
        if (!$data) {
            return $this->fail(100100);
        }

        $this->service->update($id, [$field => $value]);

        switch ($data['way']) {
            case UserWhitelist::WAY_DEVICE:
                UserWhitelistService::cacheForDevice();
                break;
            case UserWhitelist::WAY_IP:
                UserWhitelistService::cacheForIp();
                break;
            case UserWhitelist::WAY_REGION:
                UserWhitelistService::cacheForRegion();
                break;
        }

        return $this->success(100014);
    }
}
