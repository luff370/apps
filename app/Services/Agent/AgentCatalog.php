<?php

namespace App\Services\Agent;

interface AgentCatalog
{
    /**
     * 读取某个应用下已启用的智能体。
     *
     * @return array{name: string, key: string, system_prompt: string, knowledge: string, temperature: float|null, inputs: array<int, array{key: string, name: string, type: string, required: bool, desc: string}>}|null
     */
    public function findEnabled(int $appId, string $key): ?array;
}
