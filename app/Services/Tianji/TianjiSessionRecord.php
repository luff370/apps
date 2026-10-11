<?php

namespace App\Services\Tianji;

class TianjiSessionRecord
{
    /** 一条智能体对话的内存视图。 */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $appId,
        public readonly string $agentKey,
        public readonly string $title,
        public readonly ?array $report,
        public readonly string $updatedAt,
        public readonly ?array $inputs = null,
    ) {
    }
}
