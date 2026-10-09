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

    public function stream(array $messages, callable $onDelta): array
    {
        $this->calls[] = $messages;
        $content = array_shift($this->replies);
        if (!is_string($content)) {
            throw new TianjiChatException('测试回复已用完');
        }

        $length = mb_strlen($content);
        $size = max(1, (int) ceil($length / 3));
        for ($offset = 0; $offset < $length; $offset += $size) {
            $piece = mb_substr($content, $offset, $size);
            if ($piece !== '') {
                $onDelta($piece);
            }
        }

        return [
            'id' => 'test-' . count($this->calls),
            'content' => $content,
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ];
    }

    public function complete(array $messages): array
    {
        return $this->stream($messages, function () {
        });
    }
}
