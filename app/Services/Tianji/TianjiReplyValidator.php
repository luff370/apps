<?php

namespace App\Services\Tianji;

class TianjiReplyValidator
{
    public const DISCLAIMER = '说明：内容仅供传统文化研究和自我观察参考';

    public const SECTIONS = ['已知资料', '计算结果', '知识依据', '综合解读', '行动建议'];

    private const MIN_LENGTH = [
        '已知资料' => 4,
        '计算结果' => 8,
        '知识依据' => 8,
        '综合解读' => 40,
        '行动建议' => 20,
    ];

    /**
     * 检查回答是否包含规定标题、免责声明，以及技能或资料的边界。
     *
     * @return array{content: string, valid: bool, missing: string[], sections: array<string, string>}
     */
    public static function assess(string $content, string $targetType): array
    {
        $content = self::unwrap(trim($content));
        $heading = '【已知资料】';
        $start = strpos($content, $heading);
        $firstHeading = strpos($content, '【');
        if ($start !== false && $firstHeading === $start && $start > 0) {
            $content = substr($content, $start);
        }

        $sections = [];
        $missing = [];
        foreach (self::SECTIONS as $name) {
            $token = '【' . $name . '】';
            $pos = strpos($content, $token);
            if ($pos === false) {
                $sections[$name] = '';
                $missing[] = $name;
                continue;
            }

            $sections[$name] = self::sectionBody($content, $pos + strlen($token));
            if ($sections[$name] === '') {
                $missing[] = $name;
            } elseif (mb_strlen($sections[$name]) < self::MIN_LENGTH[$name]) {
                $missing[] = $name . '过短';
            }
        }

        if (!self::inOrder($content)) {
            $missing[] = '标题顺序';
        }

        if (!str_contains($content, self::DISCLAIMER)) {
            $missing[] = '说明';
        } else {
            $disclaimerAt = strpos($content, self::DISCLAIMER);
            $content = substr($content, 0, $disclaimerAt + strlen(self::DISCLAIMER));
        }

        $missing = array_merge($missing, self::boundaryIssues($sections, $targetType));

        return [
            'content' => trim($content),
            'valid' => $missing === [],
            'missing' => array_values(array_unique($missing)),
            'sections' => $sections,
        ];
    }

    /**
     * 检查技能问答和资料解读有没有越界。
     *
     * @param  array<string, string>  $sections
     * @return string[]
     */
    private static function boundaryIssues(array $sections, string $targetType): array
    {
        $issues = [];
        if ($targetType === 'skill') {
            if ($sections['已知资料'] !== '' && !str_contains($sections['已知资料'], '未选择命盘')) {
                $issues[] = '已知资料需写明未选择命盘';
            }
            if ($sections['计算结果'] !== '' && !str_contains($sections['计算结果'], '本次为知识问答，未调用命盘计算')) {
                $issues[] = '计算结果需写明本次为知识问答，未调用命盘计算';
            }
        }

        if ($targetType === 'data') {
            if ($sections['已知资料'] !== '' && str_contains($sections['已知资料'], '未选择命盘')) {
                $issues[] = '已提供计算报告时已知资料不能写未选择命盘';
            }
            if ($sections['计算结果'] !== '' && str_contains($sections['计算结果'], '本次为知识问答，未调用命盘计算')) {
                $issues[] = '已提供计算报告时计算结果不能写成知识问答';
            }
        }

        return $issues;
    }

    /** 标题必须按约定顺序出现。 */
    private static function inOrder(string $content): bool
    {
        $cursor = -1;
        foreach (self::SECTIONS as $name) {
            $pos = strpos($content, '【' . $name . '】');
            if ($pos === false) {
                continue;
            }
            if ($pos < $cursor) {
                return false;
            }
            $cursor = $pos;
        }

        return true;
    }

    /** 截取一个标题下的正文，直到下一个标题或免责声明。 */
    private static function sectionBody(string $content, int $offset): string
    {
        $rest = substr($content, $offset);
        $end = strlen($rest);
        $stops = ['说明：', '说明:'];
        foreach (self::SECTIONS as $name) {
            $stops[] = '【' . $name . '】';
        }
        foreach ($stops as $stop) {
            $pos = strpos($rest, $stop);
            if ($pos !== false && $pos < $end) {
                $end = $pos;
            }
        }

        return trim(substr($rest, 0, $end));
    }

    /** 去掉模型偶尔包在外面的代码块。 */
    private static function unwrap(string $content): string
    {
        if (preg_match('/^```(?:text|markdown)?\s*(.*?)\s*```$/su', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return $content;
    }
}
