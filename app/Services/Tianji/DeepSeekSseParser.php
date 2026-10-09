<?php

namespace App\Services\Tianji;

class DeepSeekSseParser
{
    private string $buffer = '';

    public string $id = '';

    /** @var array{prompt_tokens: int, completion_tokens: int, total_tokens: int} */
    public array $usage = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ];

    public function __construct(private $onDelta)
    {
    }

    /** 把可能被拆开的 SSE 字节流解析成文本增量。 */
    public function push(string $chunk): void
    {
        $this->buffer .= $chunk;
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = rtrim(substr($this->buffer, 0, $pos), "\r");
            $this->buffer = substr($this->buffer, $pos + 1);
            $this->consumeLine($line);
        }
    }

    private function consumeLine(string $line): void
    {
        if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) {
            return;
        }

        $payload = trim(substr($line, 5));
        if ($payload === '' || $payload === '[DONE]') {
            return;
        }

        $json = json_decode($payload, true);
        if (!is_array($json)) {
            return;
        }

        if (!empty($json['id'])) {
            $this->id = (string) $json['id'];
        }

        if (isset($json['usage']) && is_array($json['usage'])) {
            $this->usage = [
                'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($json['usage']['total_tokens'] ?? 0),
            ];
        }

        $delta = $json['choices'][0]['delta']['content'] ?? null;
        if (is_string($delta) && $delta !== '') {
            ($this->onDelta)($delta);
        }
    }
}
