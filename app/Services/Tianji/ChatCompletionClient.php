<?php

namespace App\Services\Tianji;

interface ChatCompletionClient
{
    /**
     * 调用对话模型，返回文本和 token 用量。
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{id: string, content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function complete(array $messages): array;

    /**
     * 流式调用对话模型，每收到一段文本就回调一次。
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onDelta
     * @return array{id: string, content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function stream(array $messages, callable $onDelta): array;
}
