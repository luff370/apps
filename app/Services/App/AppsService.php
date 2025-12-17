<?php

namespace App\Services\App;

use App\Dao\App\AppsDao;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use App\Support\Services\FormOptions;

/**
 * 应用service
 * Class AppsService
 */
class AppsService extends Service
{
    /**
     * form表单创建
     *
     * @var FormBuilder
     */
    protected FormBuilder $builder;

    /**
     * StoreBrandServices constructor.
     */
    public function __construct(AppsDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * @throws \App\Exceptions\AdminException
     */
    public function save($data)
    {
        if (!empty($data['id'])) {
            $this->dao->delCacheById($data['id']);

            return $this->update($data['id'], $data);
        }
        // 复制应用配置信息
        // $this->systemConfigTabServices()->syncFromOtherAppConfig(10001, intval($info['id']));

        return $this->dao->newQuery()->create($data);
    }

    /**
     * 修改应用状态
     *
     * @param int $id
     * @param $is_enable
     * @return boolean
     * @throws AdminException
     */
    public function setShow(int $id, $is_enable): bool
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $updateData = ['is_enable' => $is_enable];
        $this->dao->update($id, $updateData);

        return true;
    }

    public function getAppConfig(int $id): array
    {
        $configs = [];

        $appInfo = $this->dao->getRowByCache($id);
        if (!empty($appInfo)) {
            $configFields = [
                'logo',
                'is_enable',
                'score_switch',
                'auto_transfer_switch',
                'contact_type',
                'contact_number',
                'contact_email',
                'subscribe_switch',
                'push_channel',
                'uPush_app_key',
                'uPush_app_secret',
                'jPush_app_key',
                'jPush_app_secret',
                'ad_switch',
                'topon_app_id',
                'topon_app_key',
                'pangolin_app_id',
                'pangolin_app_key',
                'youlianghui_app_id',
                'youlianghui_app_key',
                'allowlist_switch',
                'allowlist_ad_channel',
                'splash_ad_code',
                'interstitial_ad_code',
                'native_ad_code',
                'banner_ad_code',
                'draw_ad_code',
            ];
            $configs = array_filter($appInfo, function ($key) use ($configFields) {
                return in_array($key, $configFields);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $configs;
    }
}
