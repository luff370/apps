<?php

namespace App\Services\Tianji;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DeepSeekChatClient implements ChatCompletionClient
{
    /** 调用 DeepSeek chat completions。未配置密钥或上游失败时不向外暴露细节。 */
    public function complete(array $messages): array
    {
        $config = config('chatai.channels.deepseek', []);
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            logger()->error('deepseek api key missing');
            throw new TianjiChatException('智能对话暂未开通', 503);
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) ($config['timeout'] ?? 60))
                ->acceptJson()
                ->post($baseUrl . '/chat/completions', [
                    'model' => (string) ($config['model'] ?? 'deepseek-chat'),
                    'messages' => $messages,
                    'temperature' => (float) ($config['temperature'] ?? 0.3),
                    'max_tokens' => (int) ($config['max_tokens'] ?? 4096),
                ]);
        } catch (ConnectionException $e) {
            logger()->error('deepseek connection failed', ['message' => $e->getMessage()]);
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }

        if (!$response->successful()) {
            logger()->error('deepseek chat failed', ['status' => $response->status()]);
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            logger()->error('deepseek chat returned empty content');
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }

        return [
            'id' => (string) $response->json('id', ''),
            'content' => $content,
            'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
        ];
    }

    /** 以 SSE 读取 DeepSeek，并把每个文本增量交给回调。 */
    public function stream(array $messages, callable $onDelta): array
    {
        $config = config('chatai.channels.deepseek', []);
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            logger()->error('deepseek api key missing');
            throw new TianjiChatException('智能对话暂未开通', 503);
        }

        $content = '';
        $parser = new DeepSeekSseParser(function (string $delta) use (&$content, $onDelta) {
            $content .= $delta;
            $onDelta($delta);
        });
        $status = 0;
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        $body = json_encode([
            'model' => (string) ($config['model'] ?? 'deepseek-chat'),
            'messages' => $messages,
            'temperature' => (float) ($config['temperature'] ?? 0.3),
            'max_tokens' => (int) ($config['max_tokens'] ?? 4096),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ], JSON_UNESCAPED_UNICODE);
        $handle = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 60),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$status) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                    $status = (int) $matches[1];
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$status, $parser) {
                if ($status >= 400) {
                    return strlen($data);
                }
                $parser->push($data);

                return strlen($data);
            },
        ]);

        $ok = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($ok === false || $errno !== 0) {
            logger()->error('deepseek connection failed', ['message' => $error]);
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }
        if ($status >= 400 || $status === 0) {
            logger()->error('deepseek chat failed', ['status' => $status]);
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }
        if (trim($content) === '') {
            logger()->error('deepseek chat returned empty content');
            throw new TianjiChatException('智能对话暂时不可用', 503);
        }

        return [
            'id' => $parser->id,
            'content' => $content,
            'prompt_tokens' => $parser->usage['prompt_tokens'],
            'completion_tokens' => $parser->usage['completion_tokens'],
            'total_tokens' => $parser->usage['total_tokens'],
        ];
    }
}
