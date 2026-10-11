<?php

namespace App\Services\Agent;

use App\Models\Agent;

class EloquentAgentCatalog implements AgentCatalog
{
    /** 按应用和标识读取启用中的智能体。提示词或知识为空时视为未配置。 */
    public function findEnabled(int $appId, string $key): ?array
    {
        $agent = Agent::query()
            ->where('app_id', $appId)
            ->where('agent_key', $key)
            ->where('status', 1)
            ->first();
        if (!$agent) {
            return null;
        }

        $prompt = trim((string) $agent->system_prompt);
        $knowledge = trim((string) $agent->knowledge);
        if ($prompt === '' || $knowledge === '') {
            return null;
        }

        return [
            'name' => (string) $agent->name,
            'key' => (string) $agent->agent_key,
            'system_prompt' => $prompt,
            'knowledge' => $knowledge,
            'temperature' => $agent->temperature,
            'inputs' => is_array($agent->inputs) ? $agent->inputs : [],
        ];
    }
}
