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
}
