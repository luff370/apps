<?php

namespace App\Services\Tianji;

interface TianjiChatRepository
{
    /** 新建一条绑定技能或资料的对话。 */
    public function createSession(
        int $userId,
        int $appId,
        string $targetType,
        string $targetKey,
        ?array $report,
        string $title
    ): TianjiSessionRecord;

    /** 按用户和应用读取对话，不属于当前用户时返回空。 */
    public function findOwned(int $sessionId, int $userId, int $appId): ?TianjiSessionRecord;

    /** 查找同一用户、应用和目标下最近的一条对话。 */
    public function findLatest(int $userId, int $appId, string $targetType, string $targetKey): ?TianjiSessionRecord;

    /** 保存或替换这份资料的程序计算报告。 */
    public function saveReport(int $sessionId, array $report): void;

    /**
     * 按更新时间倒序列出对话。
     *
     * @return TianjiSessionRecord[]
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
    ): TianjiMessageRecord;

    /**
     * 按时间正序返回最近若干条消息。
     *
     * @return TianjiMessageRecord[]
     */
    public function messages(int $sessionId, int $limit): array;

    /** 在一个事务里保存本轮问答。 */
    public function transaction(callable $callback): mixed;
}
