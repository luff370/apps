<?php

namespace App\Services\App;

use App\Dao\App\AppsDao;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Models\AppAgreement;
use App\Models\Merchant;
use App\Support\Services\FormBuilder;
use App\Support\Services\FormOptions;
use Illuminate\Support\Facades\DB;

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
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        foreach ($list as &$item) {

            // 域名到期警告
            $item['domain_expired_warning'] = false;
            if (!empty($item['merchant']['domain_expired_date'])) {
                $days = today()->diffInDays($item['merchant']['domain_expired_date']);
                if ($days < 30) {
                    $item['domain_expired_warning'] = true;
                }
            }
        }

        return $list;
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

        return DB::transaction(function () use ($data) {
            $app = $this->dao->newQuery()->create($data);
            $this->createAgreementsFromMerchantTemplates($app);

            return $app;
        });
    }

    /**
     * 新建应用后按主体协议母版生成应用协议，并替换协议内容里的应用名称占位符。
     */
    private function createAgreementsFromMerchantTemplates($app): void
    {
        if (empty($app['mer_id'])) {
            return;
        }

        $merchant = Merchant::query()->find((int)$app['mer_id']);
        if (!$merchant || empty($merchant['agreement_templates'])) {
            return;
        }

        foreach ($merchant['agreement_templates'] as $template) {
            if (!is_array($template) || (int)($template['status'] ?? 1) !== 1) {
                continue;
            }
            if (empty($template['title']) || empty($template['type']) || empty($template['content'])) {
                continue;
            }

            AppAgreement::query()->create([
                'app_id' => (int)$app['id'],
                'type' => (string)$template['type'],
                'platform' => (string)($template['platform'] ?? 'all'),
                'version' => 'all',
                'title' => (string)$template['title'],
                'content' => str_replace('{APP名称}', (string)$app['name'], (string)$template['content']),
                'remark' => (string)($template['remark'] ?? '由主体协议母版自动生成'),
                'sort' => 0,
                'status' => 1,
            ]);
        }
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
