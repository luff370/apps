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
     * 检查回答是否非空，并以免责声明结尾。
     *
     * @return array{content: string, valid: bool, missing: string[], sections: array<string, string>}
     */
    public static function assess(string $content): array
    {
        $content = self::unwrap(trim($content));
        $heading = '【已知资料】';
        $start = strpos($content, $heading);
        $firstHeading = strpos($content, '【');
        if ($start !== false && $firstHeading === $start && $start > 0) {
            $content = substr($content, $start);
        }

        $missing = $content === '' ? ['内容'] : [];
        $sections = [];
        foreach (self::SECTIONS as $name) {
            $token = '【' . $name . '】';
            $pos = strpos($content, $token);
            $sections[$name] = $pos === false ? '' : self::sectionBody($content, $pos + strlen($token));
        }

        if (!str_contains($content, self::DISCLAIMER)) {
            $missing[] = '说明';
        } else {
            $disclaimerAt = strpos($content, self::DISCLAIMER);
            $content = substr($content, 0, $disclaimerAt + strlen(self::DISCLAIMER));
        }

        return [
            'content' => trim($content),
            'valid' => $missing === [],
            'missing' => array_values(array_unique($missing)),
            'sections' => $sections,
        ];
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
