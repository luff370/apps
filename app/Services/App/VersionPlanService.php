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
     * 版本规划列表直接返回前端页面需要的嵌套结构，避免页面继续依赖本地假数据。
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
     * 保存计划时整体替换渠道任务，保证前端一次编辑提交后的任务顺序和强更配置一致。
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
     * 复制计划会新建主计划和任务，复制后的记录默认回到草稿态，便于运营二次调整。
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
     * 删除时校验 app_id，防止跨应用删除其它应用的版本计划。
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
        if (str_starts_with($value, $prefix)) {
            return (int)substr($value, strlen($prefix));
        }

        return 0;
    }

    private function dateOrNull($value): ?string
    {
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
