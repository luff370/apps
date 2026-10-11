<?php

namespace App\Services\Cms;

use App\Exceptions\AdminException;
use App\Models\SystemApp;
use App\Models\TianjiAgent;
use App\Services\Service;

class AgentService extends Service
{
    /** 按应用列出智能体。 */
    public function getList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $page = max($page, 1);
        $limit = min(max($limit, 1), 50);
        $query = TianjiAgent::query()->orderByDesc('id');
        if (($where['app_id'] ?? '') !== '' && $where['app_id'] !== 'all') {
            $query->where('app_id', (int) $where['app_id']);
        }
        if (($where['keyword'] ?? '') !== '') {
            $keyword = trim((string) $where['keyword']);
            $query->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('agent_key', 'like', '%' . $keyword . '%');
            });
        }
        if (($where['status'] ?? '') !== '' && $where['status'] !== 'all') {
            $query->where('status', (int) $where['status']);
        }

        $count = (clone $query)->count();
        $rows = $query->offset(($page - 1) * $limit)->limit($limit)->get();
        $apps = SystemApp::query()->pluck('name', 'id');
        $list = $rows->map(function (TianjiAgent $agent) use ($apps) {
            return [
                'id' => $agent->id,
                'app_id' => $agent->app_id,
                'app_name' => $apps[$agent->app_id] ?? '',
                'name' => $agent->name,
                'agent_key' => $agent->agent_key,
                'system_prompt' => $agent->system_prompt,
                'knowledge' => $agent->knowledge,
                'temperature' => $agent->temperature,
                'status' => (int) $agent->status,
                'remark' => $agent->remark,
                'updated_at' => optional($agent->updated_at)->format('Y-m-d H:i'),
            ];
        })->all();

        return compact('list', 'count') + ['page' => $page, 'limit' => $limit];
    }

    public function find(int $id): array
    {
        $agent = TianjiAgent::query()->find($id);
        if (!$agent) {
            throw new AdminException('智能体不存在');
        }

        return [
            'id' => $agent->id,
            'app_id' => $agent->app_id,
            'name' => $agent->name,
            'agent_key' => $agent->agent_key,
            'system_prompt' => $agent->system_prompt,
            'knowledge' => $agent->knowledge,
            'temperature' => $agent->temperature,
            'status' => (int) $agent->status,
            'remark' => $agent->remark,
            'inputs' => is_array($agent->inputs) ? $agent->inputs : [],
        ];
    }

    /** 新增或更新一个应用下的智能体。 */
    public function saveAgent(array $data): void
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = $this->payload($data);
        $exists = TianjiAgent::query()
            ->where('app_id', $payload['app_id'])
            ->where('agent_key', $payload['agent_key'])
            ->when($id > 0, fn ($query) => $query->where('id', '<>', $id))
            ->exists();
        if ($exists) {
            throw new AdminException('该应用下已有相同标识的智能体');
        }

        if ($id > 0) {
            $agent = TianjiAgent::query()->find($id);
            if (!$agent) {
                throw new AdminException('智能体不存在');
            }
            $agent->fill($payload)->save();

            return;
        }

        TianjiAgent::query()->create($payload);
    }

    public function remove(int $id): void
    {
        $agent = TianjiAgent::query()->find($id);
        if (!$agent) {
            throw new AdminException('智能体不存在');
        }
        $agent->delete();
    }

    public function setStatus(int $id, int $status): void
    {
        $agent = TianjiAgent::query()->find($id);
        if (!$agent) {
            throw new AdminException('智能体不存在');
        }
        $agent->status = $status === 1 ? 1 : 0;
        $agent->save();
    }

    /** @return array<string, mixed> */
    private function payload(array $data): array
    {
        $appId = (int) ($data['app_id'] ?? 0);
        if ($appId <= 0 || !SystemApp::query()->where('id', $appId)->exists()) {
            throw new AdminException('请选择应用');
        }

        $name = trim((string) ($data['name'] ?? ''));
        $key = trim((string) ($data['agent_key'] ?? ''));
        $prompt = trim((string) ($data['system_prompt'] ?? ''));
        $knowledge = trim((string) ($data['knowledge'] ?? ''));
        if ($name === '' || mb_strlen($name) > 32) {
            throw new AdminException('请填写智能体名称，最多 32 字');
        }
        if ($key === '' || mb_strlen($key) > 64 || preg_match('/[\x00-\x1F]/', $key) === 1) {
            throw new AdminException('标识无效，请使用 64 字以内且不含控制字符的字符串');
        }
        if ($prompt === '') {
            throw new AdminException('请填写系统提示词');
        }
        if ($knowledge === '') {
            throw new AdminException('请填写知识内容');
        }

        $temperature = $data['temperature'] ?? null;
        if ($temperature === '' || $temperature === null) {
            $temperature = null;
        } else {
            if (!is_numeric($temperature)) {
                throw new AdminException('温度必须是数字');
            }
            $temperature = (float) $temperature;
            if ($temperature < 0 || $temperature > 2) {
                throw new AdminException('温度范围是 0 到 2');
            }
        }

        return [
            'app_id' => $appId,
            'name' => $name,
            'agent_key' => $key,
            'system_prompt' => $prompt,
            'knowledge' => $knowledge,
            'temperature' => $temperature,
            'status' => (int) ($data['status'] ?? 1) === 1 ? 1 : 0,
            'remark' => trim((string) ($data['remark'] ?? '')),
            'inputs' => $this->inputs($data['inputs'] ?? []),
        ];
    }

    /** @return array<int, array{key: string, name: string, type: string, required: bool, desc: string}> */
    private function inputs(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (!is_array($raw)) {
            throw new AdminException('请求参数格式不正确');
        }

        $reserved = ['content', 'agent_key', 'session_id', 'new_session', 'payload'];
        $seen = [];
        $result = [];
        foreach (array_values($raw) as $field) {
            if (!is_array($field)) {
                throw new AdminException('请求参数格式不正确');
            }
            $key = trim((string) ($field['key'] ?? ''));
            $name = trim((string) ($field['name'] ?? ''));
            $type = (string) ($field['type'] ?? 'string');
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,31}$/', $key) || in_array($key, $reserved, true)) {
                throw new AdminException('请求参数标识无效');
            }
            if (isset($seen[$key])) {
                throw new AdminException('请求参数标识重复');
            }
            $seen[$key] = true;
            if ($name === '' || mb_strlen($name) > 32) {
                throw new AdminException('请填写请求参数名称');
            }
            if (!in_array($type, ['string', 'object'], true)) {
                throw new AdminException('请求参数类型只支持文本或对象');
            }
            $result[] = [
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'required' => !empty($field['required']),
                'desc' => mb_substr(trim((string) ($field['desc'] ?? '')), 0, 120),
            ];
        }

        return $result;
    }
}
