<?php

namespace App\Services\Agent;

interface AgentChatRepository
{
    /** 新建一条智能体对话。 */
    public function createSession(
        int $userId,
        int $appId,
        string $agentKey,
        ?array $report,
        string $title
    ): AgentSessionRecord;

    /** 按用户和应用读取对话，不属于当前用户时返回空。 */
    public function findOwned(int $sessionId, int $userId, int $appId): ?AgentSessionRecord;

    /** 查找同一用户、应用和智能体下最近的一条对话。 */
    public function findLatest(int $userId, int $appId, string $agentKey): ?AgentSessionRecord;

    /** 保存或替换这份资料的程序计算报告。 */
    public function saveReport(int $sessionId, array $report): void;

    /** 保存智能体声明的额外请求参数，例如排盘报告。 */
    public function saveInputs(int $sessionId, array $inputs): void;

    /**
     * 按更新时间倒序列出对话。
     *
     * @return AgentSessionRecord[]
     */
    public function listSessions(int $userId, int $appId, int $limit): array;

    /** 追加一条消息，并刷新对话的更新时间。 */
    public function addMessage(
        int $sessionId,
        string $role,
        string $content,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        bool $corrected,
        bool $valid
    ): AgentMessageRecord;

    /**
     * 按时间正序返回最近若干条消息。
     *
     * @return AgentMessageRecord[]
     */
    public function messages(int $sessionId, int $limit): array;

    /** 在一个事务里保存本轮问答。 */
    public function transaction(callable $callback): mixed;
}
