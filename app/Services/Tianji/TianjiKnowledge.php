<?php

namespace App\Services\Tianji;

class TianjiKnowledge
{
    private ?string $systemPrompt = null;

    /** @var array<string, string> */
    private array $excerpts = [];

    /** 读取天纪系统提示词。 */
    public function systemPrompt(): string
    {
        if ($this->systemPrompt !== null) {
            return $this->systemPrompt;
        }

        $path = (string) config('tianji.system_prompt_path');
        $prompt = $this->read($path);
        $this->systemPrompt = trim($prompt);

        return $this->systemPrompt;
    }

    /** 按索引锚点截取一个技能章节，当前只使用紫微。 */
    public function excerpt(string $skillId): string
    {
        if (isset($this->excerpts[$skillId])) {
            return $this->excerpts[$skillId];
        }

        $section = $this->section($skillId);
        $anchor = '## ' . $section['anchor'];
        $markdown = $this->read((string) config('tianji.knowledge_path'));
        $start = strpos($markdown, $anchor);
        if ($start === false) {
            throw new TianjiChatException('紫微知识库不可用', 503);
        }

        $next = strpos($markdown, "\n## ", $start + strlen($anchor));
        $excerpt = $next === false
            ? substr($markdown, $start)
            : substr($markdown, $start, $next - $start);

        $this->excerpts[$skillId] = trim($excerpt);

        return $this->excerpts[$skillId];
    }

    /**
     * 从知识索引中找到指定技能的标题锚点。
     *
     * @return array{id: string, title: string, anchor: string}
     */
    private function section(string $skillId): array
    {
        $index = json_decode($this->read((string) config('tianji.index_path')), true);
        foreach ($index['sections'] ?? [] as $section) {
            if (($section['id'] ?? '') === $skillId && !empty($section['anchor'])) {
                return $section;
            }
        }

        throw new TianjiChatException('紫微知识库不可用', 503);
    }

    /** 读取知识文件，缺失时视为知识库不可用。 */
    private function read(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            throw new TianjiChatException('紫微知识库不可用', 503);
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new TianjiChatException('紫微知识库不可用', 503);
        }

        return $contents;
    }
}
