<?php

namespace Tests\Support;

use App\Services\Tianji\ChatCompletionClient;
use App\Services\Tianji\TianjiChatException;

class FakeTianjiChatClient implements ChatCompletionClient
{
    public array $calls = [];

    public function __construct(private array $replies)
    {
    }

    public function complete(array $messages): array
    {
        $this->calls[] = $messages;
        $content = array_shift($this->replies);
        if (!is_string($content)) {
            throw new TianjiChatException('测试回复已用完');
        }

        return [
            'id' => 'test-' . count($this->calls),
            'content' => $content,
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ];
    }
}
