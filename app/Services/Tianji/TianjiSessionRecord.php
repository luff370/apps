<?php

namespace App\Services\Tianji;

class TianjiSessionRecord
{
    /** 一条天纪对话的内存视图。 */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $appId,
        public readonly string $targetType,
        public readonly string $targetKey,
        public readonly string $title,
        public readonly ?array $report,
        public readonly string $updatedAt,
    ) {
    }
}
