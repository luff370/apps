<?php

namespace App\Services\Tianji;

class TianjiMessageRecord
{
    /** 一条天纪对话消息的内存视图。 */
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
