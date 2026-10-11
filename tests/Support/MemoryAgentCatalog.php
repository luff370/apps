<?php

namespace Tests\Support;

use App\Services\Agent\AgentCatalog;

class MemoryAgentCatalog implements AgentCatalog
{
    /** @param  array<int, array{app_id: int, key: string, system_prompt: string, knowledge: string, temperature: float|null}>  $agents */
    public function __construct(private ?array $agents = null)
    {
        $this->agents ??= [[
            'app_id' => 10048,
            'key' => 'ziwei',
            'system_prompt' => '你是“倪师问诊”紫微斗数解读助手。回答必须包含【综合解读】。',
            'knowledge' => "紫微属土，帝座。\n十二宫位包括命宫和官禄宫。",
            'temperature' => null,
            'inputs' => [[
                'key' => 'report',
                'name' => '排盘报告',
                'type' => 'object',
                'required' => false,
                'desc' => '本地已经排好的命盘报告',
            ]],
        ]];
    }

    public function findEnabled(int $appId, string $key): ?array
    {
        foreach ($this->agents as $agent) {
            if ((int) $agent['app_id'] === $appId && $agent['key'] === $key) {
                return [
                    'name' => $agent['name'] ?? $key,
                    'key' => $agent['key'],
                    'system_prompt' => $agent['system_prompt'],
                    'knowledge' => $agent['knowledge'],
                    'temperature' => $agent['temperature'],
                    'inputs' => $agent['inputs'] ?? [],
                ];
            }
        }

        return null;
    }
}
