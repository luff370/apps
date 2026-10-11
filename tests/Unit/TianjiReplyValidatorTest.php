<?php

namespace Tests\Unit;

use App\Services\Tianji\TianjiReplyValidator;
use Tests\Support\TianjiChatFixtures;
use Tests\TestCase;

class TianjiReplyValidatorTest extends TestCase
{
    public function test_it_accepts_a_skill_reply_and_strips_a_fence(): void
    {
        $assessed = TianjiReplyValidator::assess("```text\n" . TianjiChatFixtures::skillReply() . "\n```");

        $this->assertTrue($assessed['valid'], implode('、', $assessed['missing']));
        $this->assertSame([], $assessed['missing']);
        $this->assertStringStartsWith('【已知资料】', $assessed['content']);
        $this->assertStringEndsWith(TianjiReplyValidator::DISCLAIMER, $assessed['content']);
        $this->assertStringContainsString('未选择命盘', $assessed['sections']['已知资料']);

        $preamble = TianjiReplyValidator::assess("好的，我来回答。\n" . TianjiChatFixtures::skillReply());
        $this->assertTrue($preamble['valid'], implode('、', $preamble['missing']));
        $this->assertStringStartsWith('【已知资料】', $preamble['content']);
    }

    public function test_it_accepts_a_short_master_reply_with_the_disclaimer(): void
    {
        $assessed = TianjiReplyValidator::assess(
            "官禄宫见武曲，事业宜按已有安排推进。\n" . TianjiReplyValidator::DISCLAIMER
        );

        $this->assertTrue($assessed['valid'], implode('、', $assessed['missing']));
        $this->assertSame([], $assessed['missing']);
    }

    public function test_it_rejects_a_reply_without_the_disclaimer(): void
    {
        $missing = str_replace("\n" . TianjiReplyValidator::DISCLAIMER, '', TianjiChatFixtures::skillReply());
        $this->assertContains('说明', TianjiReplyValidator::assess($missing)['missing']);
        $this->assertFalse(TianjiReplyValidator::assess($missing)['valid']);
    }
}
