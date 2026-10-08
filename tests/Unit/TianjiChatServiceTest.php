<?php

namespace Tests\Unit;

use App\Services\Tianji\TianjiChatException;
use App\Services\Tianji\TianjiChatService;
use App\Services\Tianji\TianjiKnowledge;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakeTianjiChatClient;
use Tests\Support\MemoryTianjiChatRepository;
use Tests\Support\TianjiChatFixtures;
use Tests\TestCase;

class TianjiChatServiceTest extends TestCase
{
    public function test_it_remembers_a_skill_conversation_and_only_sends_ziwei_knowledge(): void
    {
        $client = new FakeTianjiChatClient([
            TianjiChatFixtures::skillReply(),
            TianjiChatFixtures::skillReply(),
        ]);
        [$service, $repository] = $this->service($client);

        $first = $service->send($this->input('紫微是什么'));
        $second = $service->send($this->input('那命宫呢'));

        $this->assertSame($first['session_id'], $second['session_id']);
        $this->assertTrue($second['valid']);
        $this->assertCount(2, $client->calls);
        $this->assertSame('system', $client->calls[1][0]['role']);
        $this->assertStringContainsString('倪师问诊', $client->calls[1][0]['content']);
        $this->assertSame('user', $client->calls[1][1]['role']);
        $this->assertSame('紫微是什么', $client->calls[1][1]['content']);
        $this->assertSame('assistant', $client->calls[1][2]['role']);
        $this->assertStringContainsString('紫微', $client->calls[1][3]['content']);
        $this->assertStringContainsString('那命宫呢', $client->calls[1][3]['content']);
        $this->assertStringContainsString('未提供程序计算报告', $client->calls[1][3]['content']);
        $this->assertStringNotContainsString('乾为天', $client->calls[1][3]['content']);

        $history = $service->history(7, 10048, $first['session_id']);
        $this->assertSame(['紫微是什么', '那命宫呢'], [
            $history['messages'][0]['content'],
            $history['messages'][2]['content'],
        ]);
        $this->assertStringNotContainsString('【紫微知识片段】', $history['messages'][0]['content']);
        $this->assertCount(1, $repository->sessions);
    }

    public function test_it_keeps_a_computed_report_for_later_turns(): void
    {
        $client = new FakeTianjiChatClient([
            TianjiChatFixtures::dataReply(),
            TianjiChatFixtures::dataReply(),
        ]);
        [$service] = $this->service($client);
        $report = ['命宫' => '紫微', '官禄宫' => '武曲', '财帛宫' => '天府'];

        $first = $service->send($this->input('我的事业怎么样', 'data', 'chart-1', $report));
        $service->send([
            'user_id' => 7,
            'app_id' => 10048,
            'session_id' => $first['session_id'],
            'content' => '那财运呢',
        ]);

        $secondTurn = $client->calls[1][3]['content'];
        $this->assertStringContainsString('武曲', $secondTurn);
        $this->assertStringContainsString('那财运呢', $secondTurn);
        $history = $service->history(7, 10048, $first['session_id']);
        $this->assertSame($report, $history['session']['report']);
        $this->assertSame('那财运呢', $history['messages'][2]['content']);
    }

    public function test_it_corrects_a_malformed_reply_once(): void
    {
        $client = new FakeTianjiChatClient(['你好', TianjiChatFixtures::skillReply()]);
        [$service] = $this->service($client);

        $result = $service->send($this->input('紫微是什么'));

        $this->assertCount(2, $client->calls);
        $this->assertTrue($result['corrected']);
        $this->assertTrue($result['valid']);
        $this->assertStringStartsWith('【已知资料】', $result['result']);
        $this->assertSame('assistant', $client->calls[1][2]['role']);
        $this->assertSame('你好', $client->calls[1][2]['content']);
        $this->assertStringContainsString('格式校验', $client->calls[1][3]['content']);

        $history = $service->history(7, 10048, $result['session_id']);
        $this->assertCount(2, $history['messages']);
        $this->assertSame('assistant', $history['messages'][1]['role']);
        $this->assertStringStartsWith('【已知资料】', $history['messages'][1]['content']);
    }

    public function test_it_requires_a_computed_report_before_calling_the_model(): void
    {
        $client = new FakeTianjiChatClient([]);
        [$service] = $this->service($client);

        try {
            $service->send($this->input('我的事业怎么样', 'data', 'chart-1'));
            $this->fail('缺少报告时应拒绝');
        } catch (TianjiChatException $e) {
            $this->assertSame('个人命盘分析需要先提供程序计算报告', $e->getMessage());
        }

        $this->assertSame([], $client->calls);
    }

    public function test_it_rejects_skills_outside_ziwei(): void
    {
        $client = new FakeTianjiChatClient([]);
        [$service] = $this->service($client);

        try {
            $service->send($this->input('泰卦怎么看', 'skill', 'iching'));
            $this->fail('未开放的技能应拒绝');
        } catch (TianjiChatException $e) {
            $this->assertSame('当前仅支持紫微斗数', $e->getMessage());
        }

        $this->assertSame([], $client->calls);
    }

    public function test_it_rejects_a_report_on_a_skill_chat_and_a_target_mismatch(): void
    {
        $client = new FakeTianjiChatClient([TianjiChatFixtures::skillReply()]);
        [$service] = $this->service($client);
        $created = $service->send($this->input('紫微是什么'));

        try {
            $service->send($this->input('紫微是什么', 'skill', 'ziwei', ['命宫' => '紫微']));
            $this->fail('知识对话不应接收报告');
        } catch (TianjiChatException $e) {
            $this->assertSame('知识对话不接收命盘报告，个人解读请改为数据对话', $e->getMessage());
        }

        try {
            $service->send([
                'user_id' => 7,
                'app_id' => 10048,
                'session_id' => $created['session_id'],
                'target_type' => 'data',
                'target_key' => 'chart-1',
                'content' => '看看事业',
            ]);
            $this->fail('目标不一致时应拒绝');
        } catch (TianjiChatException $e) {
            $this->assertSame('对话与目标类型不一致', $e->getMessage());
        }
    }

    public function test_it_limits_model_history_and_starts_a_fresh_session_when_asked(): void
    {
        config(['tianji.history_messages' => 2]);
        $client = new FakeTianjiChatClient([
            TianjiChatFixtures::skillReply(),
            TianjiChatFixtures::skillReply(),
            TianjiChatFixtures::skillReply(),
            TianjiChatFixtures::skillReply(),
        ]);
        [$service] = $this->service($client);

        $service->send($this->input('第一问'));
        $service->send($this->input('第二问'));
        $service->send($this->input('第三问'));
        $fresh = $service->send($this->input('新的开始', 'skill', 'ziwei', null, true));

        $thirdCall = array_column($client->calls[2], 'content');
        $this->assertNotContains('第一问', $thirdCall);
        $this->assertContains('第二问', $thirdCall);
        $this->assertCount(2, $client->calls[3]);
        $this->assertStringContainsString('新的开始', $client->calls[3][1]['content']);
        $sessions = $service->sessions(7, 10048);
        $this->assertCount(2, $sessions['list']);
        $this->assertNotSame($sessions['list'][0]['id'], $sessions['list'][1]['id']);
        $this->assertSame($fresh['session_id'], $sessions['list'][0]['id']);
    }

    public function test_it_hides_another_users_session(): void
    {
        $client = new FakeTianjiChatClient([TianjiChatFixtures::skillReply()]);
        [$service] = $this->service($client);
        $created = $service->send($this->input('紫微是什么'));

        $this->expectException(TianjiChatException::class);
        $this->expectExceptionMessage('对话不存在');
        $service->history(8, 10048, $created['session_id']);
    }

    public function test_tianji_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertContains('api/chatAI/wenmo/message', $uris);
        $this->assertContains('api/chatAI/wenmo/sessions', $uris);
        $this->assertContains('api/chatAI/wenmo/history', $uris);
    }

    public function test_container_resolves_the_chat_service(): void
    {
        $this->assertInstanceOf(TianjiChatService::class, $this->app->make(TianjiChatService::class));
    }

    private function service(FakeTianjiChatClient $client): array
    {
        $repository = new MemoryTianjiChatRepository();

        return [
            new TianjiChatService($repository, $client, new TianjiKnowledge()),
            $repository,
        ];
    }

    private function input(
        string $content,
        string $targetType = 'skill',
        string $targetKey = 'ziwei',
        ?array $report = null,
        bool $newSession = false
    ): array {
        return [
            'user_id' => 7,
            'app_id' => 10048,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'content' => $content,
            'report' => $report,
            'new_session' => $newSession,
        ];
    }
}
