<?php

namespace Tests\Unit;

use App\Services\Tianji\TianjiKnowledge;
use Tests\TestCase;

class TianjiKnowledgeTest extends TestCase
{
    public function test_it_loads_only_the_ziwei_section_and_the_system_prompt(): void
    {
        $knowledge = new TianjiKnowledge();
        $excerpt = $knowledge->excerpt('ziwei');

        $this->assertStringContainsString('紫微', $excerpt);
        $this->assertStringContainsString('十二宫位', $excerpt);
        $this->assertStringNotContainsString('乾为天', $excerpt);
        $this->assertStringNotContainsString('地理五要', $excerpt);
        $this->assertStringContainsString('倪师问诊', $knowledge->systemPrompt());
        $this->assertStringContainsString('【综合解读】', $knowledge->systemPrompt());
    }
}
