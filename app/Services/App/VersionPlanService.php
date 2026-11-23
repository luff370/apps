<?php

namespace App\Services\App;

use App\Exceptions\AdminException;
use App\Models\AppConfig;
use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Models\SystemAdmin;
use App\Models\SystemApp;
use App\Support\Services\FormOptions;
use App\Services\Service;
use App\Services\System\AppVersionServices;
use App\Support\Services\AppConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VersionPlanService extends Service
{
    public const PLAN_STATUSES = ['开发中', '待上架', '已上架', '暂停'];

    public const TASK_STATUSES = ['待提交', '已提交', '审核中', '已拒绝', '已上架', '已下架'];

    public const PLAN_PRIORITIES = ['P1', 'P2', 'P3', 'P4'];

    public function listTasks(array $filters, array $admin): array
    {
        $owner = (string)($filters['owner'] ?? 'me');
        $query = AppVersionPlan::query()
            ->with(['tasks', 'app:id,name'])
            ->whereHas('app', fn($app) => $app->where('is_del', 0));

        if ($owner !== 'all') {
            $selected = $owner === 'me' || $owner === (string)($admin['id'] ?? '')
                ? $admin
                : (SystemAdmin::query()->where('is_del', 0)->find((int)$owner, ['account', 'real_name'])?->toArray() ?? []);
            $this->filterByOwners($query, FormOptions::adminOwnerLabels($selected));
        }

        if (!empty($filters['app_id'])) {
            $query->where('app_id', (int)$filters['app_id']);
        }
        if (in_array($filters['status'] ?? '', self::PLAN_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (in_array($filters['priority'] ?? '', self::PLAN_PRIORITIES, true)) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['channel'])) {
            $query->whereHas('tasks', fn($tasks) => $tasks->where('market_channel', $filters['channel']));
        }
        $keyword = trim((string)($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(fn($plan) => $plan->where('title', 'like', "%{$keyword}%")
                ->orWhere('version', 'like', "%{$keyword}%")
                ->orWhereHas('tasks', fn($tasks) => $tasks->where('version', 'like', "%{$keyword}%")));
        }

        $page = $query->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(min(100, max(1, (int)($filters['limit'] ?? 20))), ['*'], 'page', max(1, (int)($filters['page'] ?? 1)));

        return [
            'list' => $page->getCollection()->map(fn(AppVersionPlan $plan) => $this->formatPlan($plan) + [
                'app_id' => $plan->app_id,
                'app_name' => $plan->app?->name ?? '',
            ])->all(),
            'count' => $page->total(),
            'owner_options' => FormOptions::systemAdmins(),
            'current_admin_id' => (string)($admin['id'] ?? ''),
        ];
    }

    private function filterByOwners(Builder $query, array $labels): void
    {
        $query->where(fn($plan) => $plan->whereIn('developer_name', $labels)
            ->orWhereIn('operator_name', $labels));
    }

    /**
     * 首页待办包含当前管理员负责的开发中和待上架计划。
     */
    public function todoPlansForAdmin(array $admin): array
    {
        $query = AppVersionPlan::query();
        $this->filterByOwners($query, FormOptions::adminOwnerLabels($admin));
        $plans = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $appIds = $plans->pluck('app_id')->unique()->filter()->all();
        $appNames = $appIds === []
            ? collect()
            : SystemApp::query()->where('is_del', 0)->whereIn('id', $appIds)->pluck('name', 'id');

        $list = [];
        foreach ($plans as $plan) {
            $status = $this->normalizePlanStatus((string)$plan->status);
            $appId = (int)$plan->app_id;
            if (!in_array($status, ['开发中', '待上架'], true) || !isset($appNames[$appId])) {
                continue;
            }
            $list[] = [
                'id' => (int)$plan->id,
                'app_id' => $appId,
                'app_name' => (string)$appNames[$appId],
                'status' => $status,
                'version' => (string)$plan->version,
                'remark' => $plan->remark_is_html
                    ? trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', (string)$plan->remark)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                    : (string)($plan->remark ?? ''),
            ];
        }

        return $list;
    }

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
        if (strlen((string)($data['remark'] ?? '')) > 65535) {
            throw new AdminException('计划备注内容过长，请缩短后保存');
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
                'status' => $this->normalizePlanStatus((string)($data['status'] ?? '')),
                'priority' => $this->normalizePlanPriority((string)($data['priority'] ?? '')),
                'developer_name' => (string)($data['developer_name'] ?? $data['owner_name'] ?? $plan->developer_name ?? ''),
                'operator_name' => (string)($data['operator_name'] ?? $data['owner_name'] ?? $plan->operator_name ?? ''),
                'remark_is_html' => (bool)($data['remark_is_html'] ?? false),
                'remark' => (string)($data['remark'] ?? ''),
            ]);
            $plan->save();

            // 计划任务属于一个小集合，整体重建比逐条 diff 更稳定，也避免遗留已删除渠道。
            app(\App\Services\Risk\RiskReviewEvidence::class)->captureApp($appId);
            // 旧渠道或版本被修改、移除后，也必须让原组合的审核状态立即失效。
            $auditCacheKeys = $this->auditCacheKeysForPlan($appId, (int)$plan->id);
            AppVersionPlanTask::query()->where('plan_id', $plan->id)->delete();
            $autoAddWhiteListTasks = [];
            foreach ($data['tasks'] as $task) {
                $this->createTask($plan, $task);
                $channel = (string)($task['market_channel'] ?? '');
                $version = (string)($task['version'] ?? '');
                if ($channel !== '' && $version !== '') {
                    $auditCacheKeys[AppVersionServices::auditStatusCacheKey($appId, $channel, $version)] = true;
                    $autoAddWhiteListTasks[$channel] = $task;
                }
            }
            app(\App\Services\Risk\RiskReviewEvidence::class)->captureApp($appId);
            $syncedAutoAddWhiteList = $this->syncAutoAddWhiteListConfigs($appId, array_values($autoAddWhiteListTasks));
            if ($syncedAutoAddWhiteList) {
                DB::afterCommit(fn() => AppConfigService::cacheByAppId($appId));
            }
            if (!empty($auditCacheKeys)) {
                // 提交后再清缓存，避免其他请求在事务完成前缓存旧状态。
                DB::afterCommit(function () use ($auditCacheKeys) {
                    foreach (array_keys($auditCacheKeys) as $cacheKey) {
                        cache()->forget($cacheKey);
                    }
                });
            }
            $this->syncAppMarketsFromListedTasks($appId);

            return $this->formatPlan($plan->load('tasks'));
        });
    }

    /**
     * 复制计划会新建主计划和任务，复制后的记录默认回到开发中。
     *
     * 运营上经常会用上一版计划作为下一版模板，所以复制时保留任务、渠道、强更内容，
     * 但主计划状态回到开发中，避免把“模板副本”误当成已经上架的计划。
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
            $copy = $source->replicate(['owner_name', 'planned_release_at']);
            $copy->title = $source->title . ' 副本';
            $copy->status = '开发中';
            $copy->save();

            foreach ($source->tasks as $task) {
                $nextTask = $task->replicate(['owner_name']);
                $nextTask->plan_id = $copy->id;
                $nextTask->status = $this->normalizeTaskStatus((string)$nextTask->status);
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
            // 删除前保留原组合，删除成功后清除对应审核缓存。
            $auditCacheKeys = $this->auditCacheKeysForPlan($appId, (int)$plan->id);
            AppVersionPlanTask::query()->where('plan_id', $plan->id)->delete();
            $plan->delete();
            DB::afterCommit(function () use ($auditCacheKeys) {
                foreach (array_keys($auditCacheKeys) as $cacheKey) {
                    cache()->forget($cacheKey);
                }
            });
            $this->syncAppMarketsFromListedTasks($appId);
        });
    }

    private function auditCacheKeysForPlan(int $appId, int $planId): array
    {
        // 同一计划可能包含重复组合，使用键集合去重。
        $keys = [];
        foreach (AppVersionPlanTask::query()->where('plan_id', $planId)->get(['market_channel', 'version']) as $task) {
            $keys[AppVersionServices::auditStatusCacheKey($appId, $task->market_channel, $task->version)] = true;
        }

        return $keys;
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
            'status' => $this->normalizeTaskStatus((string)($task['status'] ?? '')),
            'submitted_at' => $this->dateTimeOrNull($task['submitted_at'] ?? null),
            'listed_at' => $this->dateTimeOrNull($task['listed_at'] ?? null),
            'remark' => (string)($task['remark'] ?? ''),
            'is_force' => (int)($task['is_force'] ?? 0),
            'force' => $this->normalizeForce($task['force'] ?? []),
        ]);
    }

    private function syncAutoAddWhiteListConfigs(int $appId, array $tasks): bool
    {
        $changed = false;
        foreach ($tasks as $task) {
            $channel = (string)($task['market_channel'] ?? '');
            $version = (string)($task['version'] ?? '');
            if ($channel === '' || $version === '') {
                continue;
            }

            $enabled = (string)($task['status'] ?? '') === '审核中' ? 1 : 0;
            $config = AppConfig::query()
                ->where('app_id', $appId)
                ->where('channel', $channel)
                ->where('key', AppConfig::AUTO_ADD_WHITE_LIST_KEY)
                ->first();

            if ($config) {
                $config->changeSource = 'version_plan';
                $next = [
                    'version' => $version,
                    'is_enable' => $enabled,
                    'remark' => AppConfig::AUTO_ADD_WHITE_LIST_REMARK,
                ];
                $dirty = false;
                foreach ($next as $field => $fieldValue) {
                    if ((string)$config->{$field} !== (string)$fieldValue) {
                        $dirty = true;
                        break;
                    }
                }
                if ($dirty) {
                    $config->fill($next)->save();
                    $changed = true;
                }
                continue;
            }

            if ($enabled !== 1) {
                continue;
            }

            $config = new AppConfig([
                'app_id' => $appId,
                'channel' => $channel,
                'version' => $version,
                'name' => AppConfig::AUTO_ADD_WHITE_LIST_NAME,
                'key' => AppConfig::AUTO_ADD_WHITE_LIST_KEY,
                'value' => '1',
                'remark' => AppConfig::AUTO_ADD_WHITE_LIST_REMARK,
                'is_enable' => 1,
            ]);
            $config->changeSource = 'version_plan';
            $config->save();
            $changed = true;
        }

        return $changed;
    }

    private function formatPlan(AppVersionPlan $plan): array
    {
        // 保持和前端旧 localStorage 结构尽量一致，减少页面改造面。
        return [
            'id' => (int)$plan->id,
            'local_id' => 'plan_' . $plan->id,
            'title' => (string)$plan->title,
            'version' => (string)$plan->version,
            'status' => $this->normalizePlanStatus((string)$plan->status),
            'priority' => $this->normalizePlanPriority((string)$plan->priority),
            'developer_name' => (string)$plan->developer_name,
            'operator_name' => (string)$plan->operator_name,
            'remark_is_html' => (bool)$plan->remark_is_html,
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
            'status' => $this->normalizeTaskStatus((string)$task->status),
            'submitted_at' => $this->dateTimeText($task->submitted_at),
            'listed_at' => $this->dateTimeText($task->listed_at),
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

    private function normalizePlanPriority(string $priority): string
    {
        $priority = strtoupper(trim($priority));

        return in_array($priority, self::PLAN_PRIORITIES, true) ? $priority : 'P2';
    }

    private function normalizePlanStatus(string $status): string
    {
        if (in_array($status, self::PLAN_STATUSES, true)) {
            return $status;
        }

        return match ($status) {
            '已完成' => '已上架',
            '已取消' => '暂停',
            '部分完成' => '待上架',
            default => '开发中',
        };
    }

    private function normalizeTaskStatus(string $status): string
    {
        if (in_array($status, self::TASK_STATUSES, true)) {
            return $status;
        }

        // 已过审、待上架已从渠道任务去掉，归到仍在推进、但还没上架的“已提交”。
        return in_array($status, ['已过审', '待上架'], true) ? '已提交' : '待提交';
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

    private function dateTimeOrNull($value): ?string
    {
        // 兼容历史纯日期数据，按本地时间保存到分钟，不引入时区转换。
        return empty($value) ? null : Carbon::parse($value)->second(0)->format('Y-m-d H:i:s');
    }

    private function dateTimeText($value): string
    {
        return empty($value) ? '' : date('Y-m-d H:i', strtotime((string)$value));
    }
}
