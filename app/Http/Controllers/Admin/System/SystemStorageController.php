<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\Other\UploadServices;
use App\Services\System\Config\SystemConfigServices;
use App\Services\System\Config\SystemStorageServices;

/**
 * Class SystemStorage
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemStorageController extends Controller
{
    /**
     * SystemStorage constructor.
     *
     * @param SystemStorageServices $services
     */
    public function __construct(SystemStorageServices $services)
    {
        $this->service = $services;
    }

    /**
     */
    public function index()
    {
        return $this->success($this->service->getList(['type' => request()->get('type')]));
    }

    /**
     * 获取创建数据表单
     *
     * @param $type
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function create($type)
    {
        if (!$type) {
            return $this->fail(100100);
        }

        return $this->success($this->service->getFormStorage((int) $type));
    }

    /**
     * 获取配置表单
     *
     * @param $type
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function getConfigForm($type)
    {
        return $this->success($this->service->getFormStorageConfig((int) $type));
    }

    /**
     * 获取配置类型
     */
    public function getConfig()
    {
        return $this->success(['type' => (int) sys_config('upload_type', 1)]);
    }

    /**
     * @param SystemConfigServices $services
     */
    public function saveConfig(SystemConfigServices $services)
    {
        $type = (int) request()->post('type', 0);
        //        $services->update('upload_type', ['value' => json_encode($type)], 'menu_name');
        //        if (1 === $type) {
        //            $this->service->transaction(function () {
        //                $this->service->update(['status' => 1, 'is_delete' => 0], ['status' => 0]);
        //            });
        //        }
        //        \App\Support\Services\CacheService::clear();

        $data = $this->getMore([
            ['accessKey', ''],
            ['secretKey', ''],
            ['appid', ''],
        ]);

        $this->service->saveConfig((int) $type, $data);

        return $this->success(100000);
    }

    /**
     * @param $type
     */
    public function synch($type)
    {
        $this->service->synchronization((int) $type);

        return $this->success(100038);
    }

    /**
     * 保存类型
     *
     * @param $type
     */
    public function save($type)
    {
        $data = $this->getMore([
            ['accessKey', ''],
            ['secretKey', ''],
            ['appid', ''],
            ['name', ''],
            ['region', ''],
            ['acl', ''],
        ]);
        $type = (int) $type;
        if ($type === 4) {
            if (!$data['appid'] && !sys_config('tengxun_appid')) {
                return $this->fail(400224);
            }
        }
        if (!$data['accessKey']) {
            unset($data['accessKey'], $data['secretKey'], $data['appid']);
        }
        $this->service->saveStorage((int) $type, $data);

        return $this->success(100021);
    }

    /**
     * 修改状态
     *
     * @param SystemConfigServices $services
     * @param $id
     */
    public function status(SystemConfigServices $services, $id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        $info = $this->service->get($id);
        $info->status = 1;
        if (!$info->domain) {
            return $this->fail(400225);
        }
        //        $services->update('upload_type', ['value' => json_encode($info->type)], 'menu_name');
        \App\Support\Services\CacheService::clear();

        //设置跨域规则
        try {
            $upload = UploadServices::init($info->type);
            $upload->setBucketCors($info->name, $info->region);
        } catch (\Throwable $e) {
        }

        //修改状态
        $this->service->transaction(function () use ($id, $info) {
            //            $this->service->update(['status' => 1, 'is_delete' => 0], ['status' => 0]);
            $this->service->update(['type' => $info->type], ['status' => 0]);
            $info->save();
        });

        return $this->success(100001);
    }

    /**
     * @param $id
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function getUpdateDomainForm($id)
    {
        return $this->success($this->service->getUpdateDomainForm((int) $id));
    }

    /**
     * @param $id
     */
    public function updateDomain($id)
    {
        $domain = request()->post('domain', '');
        $data = $this->getMore([
            ['pri', ''],
            ['ca', ''],
        ]);
        if (!$domain) {
            return $this->fail(100100);
        }
        if (strstr($domain, 'https://') === false && strstr($domain, 'http://') === false) {
            return $this->fail(400226);
        }
        //        if (strstr($domain, 'https://') !== false && !$data['pri']) {
        //            return $this->fail('域名为HTTPS访问时，必须填写证书');
        //        }

        $this->service->updateDomain($id, $domain);

        return $this->success(100001);
    }

    /**
     * 删除
     *
     * @param $id
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        if ($this->service->deleteStorage($id)) {
            return $this->success(100002);
        } else {
            return $this->fail(100008);
        }
    }

    /**
     * 切换存储类型
     *
     * @param SystemConfigServices $services
     * @param $type
     */
    public function uploadType(SystemConfigServices $services, $type)
    {
        $status = $this->service->count(['type' => $type, 'status' => 1]);
        if (!$status && $type != 1) {
            return $this->success(400227);
        }
        $services->update('upload_type', ['value' => json_encode($type)], 'menu_name');
        \App\Support\Services\CacheService::clear();
        if ($type != 1) {
            $msg = 400228;
        } else {
            $msg = 400229;
        }

        return $this->success($msg);
    }
}
