<?php

namespace App\Http\Controllers\Admin\App;

use App\Models\AppAdvertisement;
use App\Http\Controllers\Admin\Controller;
use App\Services\App\AdvertisementService;

/**
 * 协议管理
 */
class AdvertisementController extends Controller
{
    public function __construct(AdvertisementService $service)
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
        $data['ad_channels'] = AppAdvertisement::adChannelsMap();

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
            ['type', 0],
            ['market_channel', 'all'],
            ['position', ''],
            ['channels', ''],
            ['status', 1],
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
        $info = $this->service->getRow($id);
        if (empty($info)) {
            return $this->fail(100100);
        }
        $info->delete($id);

        return $this->success(100002);
    }

    /**
     * 修改协议状态
     */
    public function setStatus($id, $status)
    {
        $info = $this->service->getRow($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        $info->update(['status' => $status]);

        return $this->success(100014);
    }

    /**
     * 复制
     */
    public function copy($id)
    {
        $info = $this->service->getRow($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        AppAdvertisement::query()->create(
            [
                'app_id' => $info['app_id'],
                'title' => $info['title'],
                'market_channel' => $info['market_channel'],
                'position' => $info['position'],
                'type' => $info['type'],
                'status' => 0,
                'channels' => $info['channels'],
            ]
        );

        return $this->success(100021);
    }
}
