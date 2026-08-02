<?php

namespace App\Services\App;

use App\Exceptions\AdminException;
use App\Models\AppConfig;
use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Models\SystemApp;
use App\Services\Service;
use App\Support\Services\AppConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VersionPlanService extends Service
{
    private const REVIEW_PASSED_STATUS = '已过审';

    private const AUTO_ADD_WHITE_LIST_KEY = 'auto_add_white_list';

    /**
     * 版本规划列表直接返回前端页面需要的嵌套结构。
     *
     * 前端版本规划页最初是按本地演示数据设计的，表格、强更记录、在架渠道等模块都直接依赖
     * `plan -> tasks` 的嵌套结构。这里不再让前端额外做字段拼装，而是在后端统一整理成页面能
     * 直接消费的形状，这样真实应用和本地测试应用才能共用同一套页面逻辑。
     */
    public function listByApp(int $appId): array
    {
        if ($appId <= 0) {
            return [];
        }

        return AppVersionPlan::query()
            ->with('tasks')
            ->where('app_id', $appId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn(AppVersionPlan $plan) => $this->formatPlan($plan))
            ->values()
            ->all();
    }

    /**
     * 保存版本计划时整体替换渠道任务。
     *
     * 版本计划本质上不是一条孤立记录，而是“计划头 + 多个渠道任务”的组合。前端一次提交会
     * 同时改标题、版本号、负责人、渠道状态和强更配置，因此这里选择整单重建，而不是只 diff
     * 某个任务字段。这样可以避免旧任务残留、顺序错乱、删掉的渠道还留在数据库里的问题。
     *
     * @throws AdminException
     */
    public function savePlan(int $appId, array $data): array
    {
        if ($appId <= 0) {
            throw new AdminException('应用ID不能为空');
        }
        if (empty($data['title']) || empty($data['version'])) {
            throw new AdminException('计划名称和目标版本不能为空');
        }
        if (empty($data['tasks']) || !is_array($data['tasks'])) {
            throw new AdminException('请至少添加一个渠道任务');
        }

        return DB::transaction(function () use ($appId, $data) {
            $planId = (int)($data['id'] ?? $this->parseLocalId($data['local_id'] ?? '', 'plan_'));
            $plan = $planId > 0
                ? AppVersionPlan::query()->where('app_id', $appId)->find($planId)
                : new AppVersionPlan();

            if (!$plan) {
                throw new AdminException('版本计划不存在');
            }

            $plan->fill([
                'app_id' => $appId,
                'title' => (string)$data['title'],
                'version' => (string)$data['version'],
                'status' => (string)($data['status'] ?? '草稿'),
                'owner_name' => (string)($data['owner_name'] ?? ''),
                'planned_release_at' => $this->dateOrNull($data['planned_release_at'] ?? null),
                'remark' => (string)($data['remark'] ?? ''),
            ]);
            $plan->save();

            // 计划任务属于一个小集合，整体重建比逐条 diff 更稳定，也避免遗留已删除渠道。
            AppVersionPlanTask::query()->where('plan_id', $plan->id)->delete();
            $reviewPassedChannels = [];
            foreach ($data['tasks'] as $task) {
                $this->createTask($plan, $task);
                if ((string)($task['status'] ?? '') === self::REVIEW_PASSED_STATUS) {
                    $channel = (string)($task['market_channel'] ?? '');
                    if ($channel !== '') {
                        $reviewPassedChannels[$channel] = true;
                    }
                }
            }
            $closedAutoAddWhiteList = $this->closeAutoAddWhiteListForChannels($appId, array_keys($reviewPassedChannels));
            if ($closedAutoAddWhiteList) {
                DB::afterCommit(fn() => AppConfigService::cacheByAppId($appId));
            }
            $this->syncAppMarketsFromListedTasks($appId);

            return $this->formatPlan($plan->load('tasks'));
        });
    }

    /**
     * 复制计划会新建主计划和任务，复制后的记录默认回到草稿态。
     *
     * 运营上经常会用上一版计划作为下一版模板，所以复制时保留任务、渠道、强更内容，
     * 但主计划状态回到草稿，避免把“模板副本”误当成已经进入发布流程的计划。
     *
     * @throws AdminException
     */
    public function copyPlan(int $appId, int $id): array
    {
        $source = AppVersionPlan::query()->with('tasks')->where('app_id', $appId)->find($id);
        if (!$source) {
            throw new AdminException('版本计划不存在');
        }

        return DB::transaction(function () use ($source) {
            $copy = $source->replicate();
            $copy->title = $source->title . ' 副本';
            $copy->status = '草稿';
            $copy->save();

            foreach ($source->tasks as $task) {
                $nextTask = $task->replicate();
                $nextTask->plan_id = $copy->id;
                $nextTask->save();
            }

            return $this->formatPlan($copy->load('tasks'));
        });
    }

    /**
     * 删除版本计划前先校验 app_id。
     *
     * 这是一个典型的跨应用边界问题：如果只按 id 删除，很容易把别的应用的计划删掉。
     * 所以必须先限定在当前 app_id 范围内，再级联清理任务表。
     *
     * @throws AdminException
     */
    public function deletePlan(int $appId, int $id): void
    {
        $plan = AppVersionPlan::query()->where('app_id', $appId)->find($id);
        if (!$plan) {
            throw new AdminException('版本计划不存在');
        }

        DB::transaction(function () use ($plan) {
            $appId = (int)$plan->app_id;
            AppVersionPlanTask::query()->where('plan_id', $plan->id)->delete();
            $plan->delete();
            $this->syncAppMarketsFromListedTasks($appId);
        });
    }

    /**
     * 将版本规划里“已上架”的渠道任务同步到应用 markets 快照。
     *
     * 应用管理外层列表、应用详情、发布页读取的都是 system_apps.markets；版本规划保存时如果
     * 只写 app_version_plan_tasks，外层就仍然显示旧版本。这里把每个市场最新的一条已上架任务
     * 回写成应用当前线上渠道版本，保证前后端各入口看到的是同一份版本数据。
     */
    private function syncAppMarketsFromListedTasks(int $appId): void
    {
        $app = SystemApp::query()->where('is_del', 0)->find($appId);
        if (!$app) {
            return;
        }

        $markets = $this->marketsByChannel($app['markets'] ?? []);
        $markets = array_filter(
            $markets,
            fn($market) => (string)($market['source_type'] ?? '') !== 'version_plan'
        );
        $tasks = AppVersionPlanTask::query()
            ->select('app_version_plan_tasks.*')
            ->join('app_version_plans', 'app_version_plans.id', '=', 'app_version_plan_tasks.plan_id')
            ->where('app_version_plans.app_id', $appId)
            ->where('app_version_plan_tasks.status', '已上架')
            ->orderByRaw('COALESCE(app_version_plan_tasks.listed_at, app_version_plan_tasks.updated_at) DESC')
            ->orderByDesc('app_version_plan_tasks.id')
            ->get();

        $syncedChannels = [];
        foreach ($tasks as $task) {
            $channel = (string)$task['market_channel'];
            if ($channel === '' || isset($syncedChannels[$channel])) {
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
            $syncedChannels[$channel] = true;
        }

        // 这里只同步应用的渠道版本快照，不改变应用资料本身的更新时间。
        $app->timestamps = false;
        $app->markets = array_values($markets);
        $app->save();
        cache()->forget('system_apps:' . $appId);
    }

    /**
     * 按市场渠道整理应用 markets，便于把版本规划任务覆盖到对应渠道。
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
     * APP 版本更新时间优先取上架日期；没填上架日期时用任务更新时间兜底。
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

    private function createTask(AppVersionPlan $plan, array $task): void
    {
        if (empty($task['market_channel']) || empty($task['version'])) {
            throw new AdminException('渠道和版本号不能为空');
        }

        AppVersionPlanTask::query()->create([
            'plan_id' => (int)$plan->id,
            'market_channel' => (string)$task['market_channel'],
            'name' => (string)($task['name'] ?? ''),
            'version' => (string)$task['version'],
            'owner_name' => (string)($task['owner_name'] ?? ''),
            'status' => (string)($task['status'] ?? '待提交'),
            'submitted_at' => $this->dateOrNull($task['submitted_at'] ?? null),
            'listed_at' => $this->dateOrNull($task['listed_at'] ?? null),
            'remark' => (string)($task['remark'] ?? ''),
            'is_force' => (int)($task['is_force'] ?? 0),
            'force' => $this->normalizeForce($task['force'] ?? []),
        ]);
    }

    private function closeAutoAddWhiteListForChannels(int $appId, array $channels): bool
    {
        $channels = array_values(array_unique(array_filter($channels, fn($channel) => $channel !== '')));
        if ($appId <= 0 || empty($channels)) {
            return false;
        }

        return AppConfig::query()
            ->where('app_id', $appId)
            ->whereIn('channel', $channels)
            ->where('key', self::AUTO_ADD_WHITE_LIST_KEY)
            ->where('value', '<>', '0')
            ->update(['value' => '0']) > 0;
    }

    private function formatPlan(AppVersionPlan $plan): array
    {
        // 保持和前端旧 localStorage 结构尽量一致，减少页面改造面。
        return [
            'id' => (int)$plan->id,
            'local_id' => 'plan_' . $plan->id,
            'title' => (string)$plan->title,
            'version' => (string)$plan->version,
            'status' => (string)$plan->status,
            'owner_name' => (string)$plan->owner_name,
            'planned_release_at' => $this->dateText($plan->planned_release_at),
            'remark' => (string)($plan->remark ?? ''),
            'created_at' => $this->dateTimeText($plan->created_at),
            'updated_at' => $this->dateTimeText($plan->updated_at),
            'tasks' => $plan->tasks->map(fn(AppVersionPlanTask $task) => $this->formatTask($task))->values()->all(),
        ];
    }

    private function formatTask(AppVersionPlanTask $task): array
    {
        // 这份任务结构会被版本表格、强更记录和在架渠道共用，所以字段保持稳定很重要。
        return [
            'id' => (int)$task->id,
            'local_id' => 'task_' . $task->id,
            'market_channel' => (string)$task->market_channel,
            'name' => (string)$task->name,
            'version' => (string)$task->version,
            'owner_name' => (string)$task->owner_name,
            'status' => (string)$task->status,
            'submitted_at' => $this->dateText($task->submitted_at),
            'listed_at' => $this->dateText($task->listed_at),
            'remark' => (string)($task->remark ?? ''),
            'is_force' => (int)$task->is_force,
            'force' => $this->normalizeForce($task->force ?? []),
        ];
    }

    private function normalizeForce($force): array
    {
        // 强更配置可能只填了部分字段，这里统一成固定键，避免前端判断一堆空值。
        $force = is_array($force) ? $force : [];

        return [
            'title' => (string)($force['title'] ?? ''),
            'min_version' => (string)($force['min_version'] ?? ''),
            'effective_at' => (string)($force['effective_at'] ?? ''),
            'url' => (string)($force['url'] ?? ''),
            'info' => (string)($force['info'] ?? ''),
            'remark' => (string)($force['remark'] ?? ''),
        ];
    }

    private function parseLocalId(string $value, string $prefix): int
    {
        // 只有后端返回的 local_id 才能反解析为数据库 id，例如 plan_12、task_34。
        // 前端新建计划会临时生成 plan_时间戳_随机数，这类临时值不能当成编辑 id。
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        if (preg_match($pattern, $value, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    private function dateOrNull($value): ?string
    {
        // 前端可能传空字符串或完整时间，这里统一压成日期字段可接受的格式。
        return empty($value) ? null : substr((string)$value, 0, 10);
    }

    private function dateText($value): string
    {
        return empty($value) ? '' : date('Y-m-d', strtotime((string)$value));
    }

    private function dateTimeText($value): string
    {
        return empty($value) ? '' : date('Y-m-d H:i', strtotime((string)$value));
    }
}
