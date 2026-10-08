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
}
