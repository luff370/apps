<?php

namespace App\Http\Controllers\Admin\App;

use App\Exceptions\AdminException;
use App\Models\SystemApp;
use App\Services\App\AppsService;
use App\Http\Controllers\Admin\Controller;

/**
 * 应用管理
 */
class AppsController extends Controller
{
    public function __construct(AppsService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取应用列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['is_enable', ''],
            ['name', ''],
        ]);
        $where['is_del'] = 0;
        $data = $this->service->getAllByPage($where, ['*'], ['id' => 'desc'], ['merchant']);
        $data['market_channels'] = SystemApp::marketChannelsMap();

        return $this->success($data);
    }

    /**
     * 保存新建应用
     * @throws AdminException
     */
    public function store()
    {
        $data = $this->getMore([
            ['id', 0],
            ['name', ''],
            ['mer_id', 0],
            ['logo', ''],
            ['platform', ''],
            ['package_name', ''],
            ['markets'],
            ['is_enable', 0],
            ['score_switch', 0],
            ['auto_transfer_switch', 0],
            ['secret_key', ''],
            ['contact_type', ''],
            ['contact_number', ''],
            ['contact_email', ''],
            ['subscribe_switch', 1],
            ['push_channel', ''],
            ['uPush_app_key', ''],
            ['uPush_app_secret', ''],
            ['jPush_app_key', ''],
            ['jPush_app_secret', ''],
            ['ad_switch', 1],
            ['topon_app_id', ''],
            ['topon_app_key', ''],
            ['pangolin_app_id', ''],
            ['pangolin_app_key', ''],
            ['youlianghui_app_id', ''],
            ['youlianghui_app_key', ''],
            ['allowlist_switch', 0],
            ['allowlist_ad_channel', ''],
            ['splash_ad_code', ''],
            ['interstitial_ad_code', ''],
            ['native_ad_code', ''],
            ['banner_ad_code', ''],
            ['draw_ad_code', ''],
        ]);
        if (!$data['mer_id']) {
            return $this->fail('请选择应用所属公司主体');
        }
        $this->service->save($data);

        return $this->success('保存成功');
    }

    /**
     * 详情
     */
    public function show($id)
    {
        return $this->success($this->service->getRow($id));
    }


    /**
     * 删除应用
     */
    public function destroy($id)
    {
        $this->service->softDel($id);

        return $this->success(100002);
    }

    /**
     * 修改应用状态
     * @throws AdminException
     */
    public function setStatus($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->setShow($id, $status);

        return $this->success(100014);
    }
}
