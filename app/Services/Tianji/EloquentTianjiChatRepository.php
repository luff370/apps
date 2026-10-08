<?php

namespace App\Services\Tianji;

use App\Models\TianjiChatMessage;
use App\Models\TianjiChatSession;
use Illuminate\Support\Facades\DB;

class EloquentTianjiChatRepository implements TianjiChatRepository
{
    /** 新建一条绑定技能或资料的对话。 */
    public function createSession(
        int $userId,
        int $appId,
        string $targetType,
        string $targetKey,
        ?array $report,
        string $title
    ): TianjiSessionRecord {
        $session = TianjiChatSession::query()->create([
            'user_id' => $userId,
            'app_id' => $appId,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'title' => $title,
            'report' => $report,
        ]);

        return $this->sessionRecord($session);
    }

    /** 按用户和应用读取对话，不属于当前用户时返回空。 */
    public function findOwned(int $sessionId, int $userId, int $appId): ?TianjiSessionRecord
    {
        $session = TianjiChatSession::query()
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->first();

        return $session ? $this->sessionRecord($session) : null;
    }

    /** 查找同一用户、应用和目标下最近的一条对话。 */
    public function findLatest(int $userId, int $appId, string $targetType, string $targetKey): ?TianjiSessionRecord
    {
        $session = TianjiChatSession::query()
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->where('target_type', $targetType)
            ->where('target_key', $targetKey)
            ->orderByDesc('id')
            ->first();

        return $session ? $this->sessionRecord($session) : null;
    }

    /** 保存或替换这份资料的程序计算报告。 */
    public function saveReport(int $sessionId, array $report): void
    {
        $session = TianjiChatSession::query()->find($sessionId);
        if (!$session) {
            return;
        }

        $session->report = $report;
        $session->save();
    }

    /** 按更新时间倒序列出对话。 */
    public function listSessions(int $userId, int $appId, int $limit): array
    {
        return TianjiChatSession::query()
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (TianjiChatSession $session) => $this->sessionRecord($session))
            ->all();
    }

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
    ): TianjiMessageRecord {
        $message = TianjiChatMessage::query()->create([
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'corrected' => $corrected,
            'valid' => $valid,
        ]);
        TianjiChatSession::query()->where('id', $sessionId)->update(['updated_at' => now()]);

        return $this->messageRecord($message);
    }

    /** 按时间正序返回最近若干条消息。 */
    public function messages(int $sessionId, int $limit): array
    {
        return TianjiChatMessage::query()
            ->where('session_id', $sessionId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (TianjiChatMessage $message) => $this->messageRecord($message))
            ->values()
            ->all();
    }

    /** 在一个事务里保存本轮问答。 */
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** 把会话模型转成对话记录。 */
    private function sessionRecord(TianjiChatSession $session): TianjiSessionRecord
    {
        return new TianjiSessionRecord(
            (int) $session->id,
            (int) $session->user_id,
            (int) $session->app_id,
            (string) $session->target_type,
            (string) $session->target_key,
            (string) $session->title,
            $session->report,
            optional($session->updated_at)->format('Y-m-d H:i:s') ?? '',
        );
    }

    /** 把消息模型转成消息记录。 */
    private function messageRecord(TianjiChatMessage $message): TianjiMessageRecord
    {
        return new TianjiMessageRecord(
            (int) $message->id,
            (int) $message->session_id,
            (string) $message->role,
            (string) $message->content,
            (bool) $message->corrected,
            (bool) $message->valid,
            optional($message->created_at)->format('Y-m-d H:i:s') ?? '',
        );
    }
}
