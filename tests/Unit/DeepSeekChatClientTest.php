<?php

namespace Tests\Unit;

use App\Services\Tianji\DeepSeekChatClient;
use App\Services\Tianji\TianjiChatException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekChatClientTest extends TestCase
{
    public function test_it_posts_chat_completions_with_the_configured_model(): void
    {
        config([
            'chatai.channels.deepseek.api_key' => 'sk-test',
            'chatai.channels.deepseek.base_url' => 'https://api.deepseek.com',
            'chatai.channels.deepseek.model' => 'deepseek-chat',
        ]);
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'chatcmpl-1',
                'choices' => [['message' => ['content' => '回答']]],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 4, 'total_tokens' => 7],
            ]),
        ]);

        $result = (new DeepSeekChatClient())->complete([
            ['role' => 'user', 'content' => '紫微是什么'],
        ]);

        $this->assertSame('回答', $result['content']);
        $this->assertSame(7, $result['total_tokens']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === 'deepseek-chat'
                && $request['messages'][0]['content'] === '紫微是什么';
        });
    }

    public function test_it_does_not_call_deepseek_when_the_key_is_missing(): void
    {
        config(['chatai.channels.deepseek.api_key' => '']);
        Http::fake();

        $this->expectException(TianjiChatException::class);
        $this->expectExceptionMessage('智能对话暂未开通');
        try {
            (new DeepSeekChatClient())->complete([['role' => 'user', 'content' => '你好']]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_hides_upstream_failures(): void
    {
        config(['chatai.channels.deepseek.api_key' => 'sk-test']);
        Http::fake([
            'https://api.deepseek.com/*' => Http::response(['error' => ['message' => 'bad key']], 401),
        ]);

        $this->expectException(TianjiChatException::class);
        $this->expectExceptionMessage('智能对话暂时不可用');
        (new DeepSeekChatClient())->complete([['role' => 'user', 'content' => '你好']]);
    }

    public function test_it_hides_connection_failures(): void
    {
        config(['chatai.channels.deepseek.api_key' => 'sk-test']);
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->expectException(TianjiChatException::class);
        $this->expectExceptionMessage('智能对话暂时不可用');
        (new DeepSeekChatClient())->complete([['role' => 'user', 'content' => '你好']]);
    }
}

class DeepSeekSseParserTest extends TestCase
{
    public function test_it_emits_deltas_when_chunks_split_a_line(): void
    {
        $deltas = '';
        $parser = new \App\Services\Tianji\DeepSeekSseParser(function (string $delta) use (&$deltas) {
            $deltas .= $delta;
        });

        $parser->push("data: {\"choices\":[{\"delta\":{\"content\":\"紫\"}}]}\n");
        $parser->push("data: {\"id\":\"chatcmpl-1\",\"choices\":[{\"delta\":{\"con");
        $parser->push("tent\":\"微\"}}]}\r\n\r\n");
        $parser->push("data: {\"choices\":[],\"usage\":{\"prompt_tokens\":3,\"completion_tokens\":4,\"total_tokens\":7}}\n");
        $parser->push("data: [DONE]\n");

        $this->assertSame('紫微', $deltas);
        $this->assertSame('chatcmpl-1', $parser->id);
        $this->assertSame(7, $parser->usage['total_tokens']);
    }
}
