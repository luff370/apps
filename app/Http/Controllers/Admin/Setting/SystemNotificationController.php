<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Support\Services\CacheService;
use App\Services\Message\SystemNotificationServices;

/**
 * Class SystemRole
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemNotificationController extends Controller
{
    /**
     * SystemRole constructor.
     *
     * @param SystemNotificationServices $services
     */
    public function __construct(SystemNotificationServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['type', ''],
        ]);

        return $this->success($this->service->getNotList($where));
    }

    /**
     * 显示编辑
     */
    public function info()
    {
        $where = $this->getMore([
            ['type', ''],
            ['id', 0],
        ]);
        if (!$where['id']) {
            return $this->fail(100100);
        }

        return $this->success($this->service->getNotInfo($where));
    }

    /**
     * 保存新建的资源
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function save()
    {
        $data = $this->getMore([
            ['id', 0],
            ['type', ''],
            ['name', ''],
            ['title', ''],
            ['is_system', 0],
            ['is_app', 0],
            ['is_wechat', 0],
            ['is_routine', 0],
            ['is_sms', 0],
            ['is_ent_wechat', 0],
            ['system_title', ''],
            ['system_text', ''],
            ['tempid', ''],
            ['ent_wechat_text', ''],
            ['url', ''],
            ['wechat_id', ''],
            ['routine_id', ''],
            ['mark', ''],
            ['is_wechat_group', ''],
            ['wechat_group_text', ''],
            ['is_fly_book', ''],
            ['fly_book_text', ''],
            ['fly_book_url', ''],
        ]);

        if (!$data['id']) {
            return $this->fail(100100);
        }
        $this->service->saveData($data);

        return $this->success(100001);
    }

    /**
     * 修改消息状态
     *
     * @param $type
     * @param $status
     * @param $id
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set_status($type, $status, $id)
    {
        if ($type == '' || $status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, [$type => $status]);
        $res = $this->service->getOneNotce(['id' => $id]);
        CacheService::delete('NOTICE_SMS_' . $res->mark);
        CacheService::delete('wechat_' . $res->mark);
        CacheService::delete('routine_' . $res->mark);
        CacheService::delete('TEMP_IDS_LIST');

        return $this->success(100014);
    }
}
