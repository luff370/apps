<?php

namespace App\Services\App;

use App\Dao\App\AppsDao;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Models\AppAgreement;
use App\Models\AppVersionPlanTask;
use App\Models\Merchant;
use App\Support\Services\FormBuilder;
use App\Support\Services\FormOptions;
use Carbon\Carbon;
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
        $appIds = collect($list)->pluck('id')->filter()->map(fn($id) => (int)$id)->values()->all();
        $listedTasks = $this->listedVersionTasksByAppIds($appIds);

        foreach ($list as &$item) {
            $this->applyVersionPlanSnapshot($item, $listedTasks[(int)$item['id']] ?? []);

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
     * 应用详情也需要和版本规划保持同一套“当前在架版本”口径。
     *
     * 应用管理列表、应用详情、版本规划页都会展示渠道版本。如果只在前端用 system_apps.markets
     * 计算，版本规划里已经标记“已上架”的新版本不会同步到外层应用管理。这里在读取应用时把
     * 已上架的版本规划任务覆盖到 markets 快照上，保证各入口看到的版本一致。
     */
    public function getRow($id, array $field = ['*'], array $with = [])
    {
        $row = parent::getRow($id, $field, $with);
        if ($row) {
            $listedTasks = $this->listedVersionTasksByAppIds([(int)$row['id']]);
            $this->applyVersionPlanSnapshot($row, $listedTasks[(int)$row['id']] ?? []);
        }

        return $row;
    }

    /**
     * 批量读取已上架的版本规划任务。
     *
     * 只读取“已上架”的任务，是因为外层应用管理展示的是当前线上版本；待提交、审核中、
     * 已拒绝等流程状态不应该覆盖线上版本。排序放在 SQL 层，后面按渠道取第一条即可得到
     * 每个应用、每个市场最新的一条已上架记录。
     */
    private function listedVersionTasksByAppIds(array $appIds): array
    {
        $appIds = array_values(array_unique(array_filter(array_map('intval', $appIds))));
        if (!$appIds) {
            return [];
        }

        $rows = AppVersionPlanTask::query()
            ->select('app_version_plan_tasks.*', 'app_version_plans.app_id')
            ->join('app_version_plans', 'app_version_plans.id', '=', 'app_version_plan_tasks.plan_id')
            ->whereIn('app_version_plans.app_id', $appIds)
            ->where('app_version_plan_tasks.status', '已上架')
            ->orderByRaw('COALESCE(app_version_plan_tasks.listed_at, app_version_plan_tasks.updated_at) DESC')
            ->orderByDesc('app_version_plan_tasks.id')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['app_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * 把版本规划任务投影到应用 markets，并计算“APP版本未更天数”。
     *
     * 未更天数的业务含义是：距离 APP 当前线上版本最后一次上架/更新过去了多少天。
     * 因此优先使用已上架任务的 listed_at；没有填写上架日时，才退回任务更新时间。
     */
    private function applyVersionPlanSnapshot(&$app, array $listedTasks): void
    {
        $markets = $this->marketsByChannel($app['markets'] ?? []);
        $latestVersionUpdatedAt = null;

        foreach ($listedTasks as $task) {
            $channel = (string)$task['market_channel'];
            if ($channel === '') {
                continue;
            }

            $versionUpdatedAt = $this->versionUpdatedAt($task['listed_at'] ?? null, $task['updated_at'] ?? null);
            $markets[$channel] = array_merge($markets[$channel] ?? [], [
                'market_channel' => $channel,
                'name' => (string)($task['name'] ?? ($markets[$channel]['name'] ?? '')),
                'status' => 1,
                'version' => (string)$task['version'],
                'date' => $versionUpdatedAt ? $versionUpdatedAt->format('Y-m-d') : '',
                'remark' => (string)($task['remark'] ?? ''),
                'source_type' => 'version_plan',
                'source_task_id' => (int)$task['id'],
            ]);

            $latestVersionUpdatedAt = $this->maxCarbon($latestVersionUpdatedAt, $versionUpdatedAt);
        }

        foreach ($markets as $market) {
            if ((int)($market['status'] ?? 0) !== 1) {
                continue;
            }
            $latestVersionUpdatedAt = $this->maxCarbon(
                $latestVersionUpdatedAt,
                $this->parseDateTime($market['date'] ?? null)
            );
        }

        $app['markets'] = array_values($markets);
        $app['last_version_update_at'] = $latestVersionUpdatedAt ? $latestVersionUpdatedAt->format('Y-m-d') : '';
        $app['stale_days'] = $latestVersionUpdatedAt ? $this->staleDaysFrom($latestVersionUpdatedAt) : null;
    }

    /**
     * 按渠道整理 markets，方便版本规划任务按 market_channel 覆盖对应市场。
     */
    private function marketsByChannel($markets): array
    {
        $result = [];
        foreach (is_array($markets) ? $markets : [] as $market) {
            if (!is_array($market)) {
                continue;
            }
            $channel = (string)($market['market_channel'] ?? '');
            if ($channel === '') {
                continue;
            }
            $result[$channel] = $market;
        }

        return $result;
    }

    /**
     * 版本更新时间优先取上架日期，未填写时使用任务更新时间兜底。
     */
    private function versionUpdatedAt($listedAt, $updatedAt): ?Carbon
    {
        return $this->parseDateTime($listedAt) ?: $this->parseDateTime($updatedAt);
    }

    private function parseDateTime($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function maxCarbon(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if (!$left) {
            return $right;
        }
        if (!$right) {
            return $left;
        }

        return $right->greaterThan($left) ? $right : $left;
    }

    private function staleDaysFrom(Carbon $versionUpdatedAt): int
    {
        if ($versionUpdatedAt->greaterThan(today())) {
            return 0;
        }

        return (int)$versionUpdatedAt->diffInDays(today());
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
