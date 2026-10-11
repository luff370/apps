<?php

namespace App\Services\Agent;

class AgentMessageRecord
{
    /** 一条智能体对话消息的内存视图。 */
    public function __construct(
        public readonly int $id,
        public readonly int $sessionId,
        public readonly string $role,
        public readonly string $content,
        public readonly bool $corrected,
        public readonly bool $valid,
        public readonly string $createdAt,
    ) {
    }
}
