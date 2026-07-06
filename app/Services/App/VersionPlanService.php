<?php

namespace App\Services\App;

use App\Exceptions\AdminException;
use App\Models\AppVersionPlan;
use App\Models\AppVersionPlanTask;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class VersionPlanService extends Service
{
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
            foreach ($data['tasks'] as $task) {
                $this->createTask($plan, $task);
            }

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
            AppVersionPlanTask::query()->where('plan_id', $plan->id)->delete();
            $plan->delete();
        });
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
