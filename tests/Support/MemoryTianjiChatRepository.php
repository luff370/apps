<?php

namespace Tests\Support;

use App\Services\Tianji\TianjiChatRepository;
use App\Services\Tianji\TianjiMessageRecord;
use App\Services\Tianji\TianjiSessionRecord;

class MemoryTianjiChatRepository implements TianjiChatRepository
{
    /** @var array<int, TianjiSessionRecord> */
    public array $sessions = [];

    /** @var array<int, TianjiMessageRecord> */
    public array $messages = [];

    private int $sessionId = 0;

    private int $messageId = 0;

    private int $ticks = 0;

    public function createSession(
        int $userId,
        int $appId,
        string $targetType,
        string $targetKey,
        ?array $report,
        string $title,
        string $agentKey = ''
    ): TianjiSessionRecord {
        $this->sessionId++;
        $session = new TianjiSessionRecord(
            $this->sessionId,
            $userId,
            $appId,
            $targetType,
            $targetKey,
            $title,
            $report,
            $this->now(),
            $agentKey,
            null
        );
        $this->sessions[$session->id] = $session;

        return $session;
    }

    public function findOwned(int $sessionId, int $userId, int $appId): ?TianjiSessionRecord
    {
        $session = $this->sessions[$sessionId] ?? null;
        if (!$session || $session->userId !== $userId || $session->appId !== $appId) {
            return null;
        }

        return $session;
    }

    public function findLatest(int $userId, int $appId, string $targetType, string $targetKey, string $agentKey = ''): ?TianjiSessionRecord
    {
        $found = null;
        foreach ($this->sessions as $session) {
            if ($session->userId !== $userId || $session->appId !== $appId) {
                continue;
            }
            if ($session->targetType !== $targetType || $session->targetKey !== $targetKey) {
                continue;
            }
            if ($agentKey !== '' && $session->agentKey !== $agentKey) {
                continue;
            }
            if ($found === null || $session->id > $found->id) {
                $found = $session;
            }
        }

        return $found;
    }

    public function saveInputs(int $sessionId, array $inputs): void
    {
        $session = $this->sessions[$sessionId];
        $report = isset($inputs['report']) && is_array($inputs['report']) ? $inputs['report'] : $session->report;
        $this->sessions[$sessionId] = new TianjiSessionRecord(
            $session->id,
            $session->userId,
            $session->appId,
            $session->targetType,
            $session->targetKey,
            $session->title,
            $report,
            $this->now(),
            $session->agentKey,
            $inputs
        );
    }

    public function saveReport(int $sessionId, array $report): void
    {
        $session = $this->sessions[$sessionId];
        $this->sessions[$sessionId] = new TianjiSessionRecord(
            $session->id,
            $session->userId,
            $session->appId,
            $session->targetType,
            $session->targetKey,
            $session->title,
            $report,
            $this->now(),
            $session->agentKey,
            $session->inputs
        );
    }

    public function listSessions(int $userId, int $appId, int $limit): array
    {
        $rows = array_values(array_filter(
            $this->sessions,
            fn (TianjiSessionRecord $session) => $session->userId === $userId && $session->appId === $appId
        ));
        usort($rows, fn (TianjiSessionRecord $a, TianjiSessionRecord $b) => [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);

        return array_slice($rows, 0, $limit);
    }

    public function addMessage(
        int $sessionId,
        string $role,
        string $content,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        bool $corrected,
        bool $valid
    ): TianjiMessageRecord {
        $this->messageId++;
        $message = new TianjiMessageRecord(
            $this->messageId,
            $sessionId,
            $role,
            $content,
            $corrected,
            $valid,
            $this->now()
        );
        $this->messages[] = $message;
        $session = $this->sessions[$sessionId];
        $this->sessions[$sessionId] = new TianjiSessionRecord(
            $session->id,
            $session->userId,
            $session->appId,
            $session->targetType,
            $session->targetKey,
            $session->title,
            $session->report,
            $message->createdAt,
            $session->agentKey,
            $session->inputs
        );

        return $message;
    }

    public function messages(int $sessionId, int $limit): array
    {
        $rows = [];
        foreach ($this->messages as $message) {
            if ($message->sessionId === $sessionId) {
                $rows[] = $message;
            }
        }

        return array_slice($rows, -$limit);
    }

    public function transaction(callable $callback): mixed
    {
        return $callback();
    }

    private function now(): string
    {
        $this->ticks++;

        return date('Y-m-d H:i:s', 1700000000 + $this->ticks);
    }
}
