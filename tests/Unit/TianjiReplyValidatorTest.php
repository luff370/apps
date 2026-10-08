<?php

namespace Tests\Unit;

use App\Services\Tianji\TianjiReplyValidator;
use Tests\Support\TianjiChatFixtures;
use Tests\TestCase;

class TianjiReplyValidatorTest extends TestCase
{
    public function test_it_accepts_a_skill_reply_and_strips_a_fence(): void
    {
        $assessed = TianjiReplyValidator::assess("```text\n" . TianjiChatFixtures::skillReply() . "\n```", 'skill');

        $this->assertTrue($assessed['valid'], implode('、', $assessed['missing']));
        $this->assertSame([], $assessed['missing']);
        $this->assertStringStartsWith('【已知资料】', $assessed['content']);
        $this->assertStringEndsWith(TianjiReplyValidator::DISCLAIMER, $assessed['content']);
        $this->assertStringContainsString('未选择命盘', $assessed['sections']['已知资料']);

        $preamble = TianjiReplyValidator::assess("好的，我来回答。\n" . TianjiChatFixtures::skillReply(), 'skill');
        $this->assertTrue($preamble['valid'], implode('、', $preamble['missing']));
        $this->assertStringStartsWith('【已知资料】', $preamble['content']);
    }

    public function test_it_rejects_a_personal_conclusion_when_no_chart_was_selected(): void
    {
        $reply = str_replace(
            '本次为知识问答，未调用命盘计算。',
            '命宫落在紫微，今年事业一定顺利。',
            TianjiChatFixtures::skillReply()
        );

        $assessed = TianjiReplyValidator::assess($reply, 'skill');

        $this->assertFalse($assessed['valid']);
        $this->assertContains('计算结果需写明本次为知识问答，未调用命盘计算', $assessed['missing']);
    }

    public function test_it_rejects_a_data_reply_that_claims_no_chart(): void
    {
        $reply = str_replace('使用资料 chart-1。', '未选择命盘。', TianjiChatFixtures::dataReply());
        $assessed = TianjiReplyValidator::assess($reply, 'data');

        $this->assertFalse($assessed['valid']);
        $this->assertContains('已提供计算报告时已知资料不能写未选择命盘', $assessed['missing']);
    }

    public function test_it_rejects_missing_disclaimer_and_wrong_order(): void
    {
        $missing = str_replace("\n" . TianjiReplyValidator::DISCLAIMER, '', TianjiChatFixtures::skillReply());
        $this->assertContains('说明', TianjiReplyValidator::assess($missing, 'skill')['missing']);

        $swapped = str_replace(
            "【已知资料】\n未选择命盘。问题是紫微星主管什么。\n\n【计算结果】\n本次为知识问答，未调用命盘计算。",
            "【计算结果】\n本次为知识问答，未调用命盘计算。\n\n【已知资料】\n未选择命盘。问题是紫微星主管什么。",
            TianjiChatFixtures::skillReply()
        );
        $this->assertContains('标题顺序', TianjiReplyValidator::assess($swapped, 'skill')['missing']);
    }
}
