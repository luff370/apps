<?php

use App\Models\SystemApiInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $report = [
            'key' => 'report',
            'type' => 'object',
            'required' => false,
            'desc' => '程序计算报告。仅 data 对话使用，字段由排盘结果决定；同一资料后续可省略',
            'example' => ['命宫' => '紫微', '官禄宫' => '武曲', '财帛宫' => '天府'],
            'properties' => [
                ['key' => '命宫', 'type' => 'string', 'desc' => '命宫主星', 'example' => '紫微'],
                ['key' => '官禄宫', 'type' => 'string', 'desc' => '官禄宫主星', 'example' => '武曲'],
                ['key' => '财帛宫', 'type' => 'string', 'desc' => '财帛宫主星', 'example' => '天府'],
            ],
        ];
        $sections = [
            'key' => 'sections',
            'type' => 'object',
            'desc' => '按固定标题拆开的回答',
            'example' => [
                '已知资料' => '未选择命盘。问题是紫微星主管什么。',
                '计算结果' => '本次为知识问答，未调用命盘计算。',
                '知识依据' => '紫微属土，帝座，主尊贵、权威。',
                '综合解读' => '紫微是十四主星中的帝座，核心含义是尊贵与权威。',
                '行动建议' => '讨论个人事业前先选择命盘。',
            ],
            'properties' => [
                ['key' => '已知资料', 'type' => 'string', 'desc' => '实际使用的命盘、报告或问题', 'example' => '未选择命盘'],
                ['key' => '计算结果', 'type' => 'string', 'desc' => '程序结果；知识问答固定说明未调用命盘计算', 'example' => '本次为知识问答，未调用命盘计算。'],
                ['key' => '知识依据', 'type' => 'string', 'desc' => '引用的紫微知识', 'example' => '紫微属土，主尊贵、权威。'],
                ['key' => '综合解读', 'type' => 'string', 'desc' => '核心判断和推理', 'example' => '紫微是帝座星，主尊贵与权威。'],
                ['key' => '行动建议', 'type' => 'string', 'desc' => '按优先级给出的观察建议', 'example' => '讨论个人事业前先选择命盘。'],
            ],
        ];

        $rows = [
            [
                'name' => '文墨对话',
                'path' => 'chatAI/wenmo/message',
                'remark' => '围绕一份紫微知识或一份程序计算资料对话，并记住上下文',
                'request_params' => [
                    ['key' => 'session_id', 'type' => 'integer', 'required' => false, 'desc' => '已有对话 ID。传入后忽略自动续接', 'example' => 1],
                    ['key' => 'new_session', 'type' => 'boolean', 'required' => false, 'desc' => '为 true 时新开对话，不续接同目标的最近会话', 'example' => false],
                    ['key' => 'target_type', 'type' => 'string', 'required' => false, 'desc' => 'skill 为紫微知识，data 为程序计算资料。新建时必填', 'example' => 'skill'],
                    ['key' => 'target_key', 'type' => 'string', 'required' => false, 'desc' => '技能固定 ziwei；资料填命盘 ID。新建时必填', 'example' => 'ziwei'],
                    ['key' => 'content', 'type' => 'string', 'required' => true, 'desc' => '用户问题，最多 2000 字', 'example' => '紫微星主管什么？'],
                    $report,
                ],
                'response_params' => [
                    ['key' => 'session_id', 'type' => 'integer', 'desc' => '对话 ID，后续续接时回传', 'example' => 1],
                    ['key' => 'message_id', 'type' => 'integer', 'desc' => '本轮助手消息 ID', 'example' => 2],
                    ['key' => 'target_type', 'type' => 'string', 'desc' => 'skill 或 data', 'example' => 'skill'],
                    ['key' => 'target_key', 'type' => 'string', 'desc' => '技能标识或资料标识', 'example' => 'ziwei'],
                    ['key' => 'result', 'type' => 'string', 'desc' => '完整回答，以免责声明结尾', 'example' => "【已知资料】\n未选择命盘。\n\n说明：内容仅供传统文化研究和自我观察参考"],
                    $sections,
                    ['key' => 'valid', 'type' => 'boolean', 'desc' => '是否通过输出格式校验', 'example' => true],
                    ['key' => 'missing', 'type' => 'array', 'desc' => '仍缺失的标题或边界，通过时为空数组', 'example' => []],
                    ['key' => 'corrected', 'type' => 'boolean', 'desc' => '是否已经自动重写过一次', 'example' => false],
                ],
            ],
            [
                'name' => '文墨对话列表',
                'path' => 'chatAI/wenmo/sessions',
                'remark' => '当前用户最近 20 条文墨对话',
                'request_params' => [],
                'response_params' => [
                    [
                        'key' => 'list',
                        'type' => 'array',
                        'desc' => '对话摘要，按更新时间倒序',
                        'example' => [[
                            'id' => 1,
                            'target_type' => 'skill',
                            'target_key' => 'ziwei',
                            'title' => '紫微星主管什么',
                            'has_report' => false,
                            'updated_at' => '2026-10-08 15:00:00',
                        ]],
                        'items' => [
                            ['key' => 'id', 'type' => 'integer', 'desc' => '对话 ID', 'example' => 1],
                            ['key' => 'target_type', 'type' => 'string', 'desc' => 'skill 或 data', 'example' => 'skill'],
                            ['key' => 'target_key', 'type' => 'string', 'desc' => '技能标识或资料标识', 'example' => 'ziwei'],
                            ['key' => 'title', 'type' => 'string', 'desc' => '首问摘要', 'example' => '紫微星主管什么'],
                            ['key' => 'has_report', 'type' => 'boolean', 'desc' => '是否已保存程序计算报告', 'example' => false],
                            ['key' => 'updated_at', 'type' => 'string', 'desc' => '最近更新时间', 'example' => '2026-10-08 15:00:00'],
                        ],
                    ],
                ],
            ],
            [
                'name' => '文墨对话记录',
                'path' => 'chatAI/wenmo/history',
                'remark' => '读取一条文墨对话的历史消息和已保存报告',
                'request_params' => [
                    ['key' => 'session_id', 'type' => 'integer', 'required' => true, 'desc' => '对话 ID', 'example' => 1],
                ],
                'response_params' => [
                    [
                        'key' => 'session',
                        'type' => 'object',
                        'desc' => '对话信息和已保存报告',
                        'example' => [
                            'id' => 1,
                            'target_type' => 'data',
                            'target_key' => 'chart-1001',
                            'title' => '我的事业怎么样',
                            'has_report' => true,
                            'report' => ['命宫' => '紫微', '官禄宫' => '武曲'],
                            'updated_at' => '2026-10-08 15:00:00',
                        ],
                        'properties' => [
                            ['key' => 'id', 'type' => 'integer', 'desc' => '对话 ID', 'example' => 1],
                            ['key' => 'target_type', 'type' => 'string', 'desc' => 'skill 或 data', 'example' => 'data'],
                            ['key' => 'target_key', 'type' => 'string', 'desc' => '技能标识或资料标识', 'example' => 'chart-1001'],
                            ['key' => 'title', 'type' => 'string', 'desc' => '首问摘要', 'example' => '我的事业怎么样'],
                            ['key' => 'has_report', 'type' => 'boolean', 'desc' => '是否已保存程序计算报告', 'example' => true],
                            ['key' => 'report', 'type' => 'object', 'desc' => '已保存的程序计算报告，知识对话为 null', 'example' => ['命宫' => '紫微']],
                            ['key' => 'updated_at', 'type' => 'string', 'desc' => '最近更新时间', 'example' => '2026-10-08 15:00:00'],
                        ],
                    ],
                    [
                        'key' => 'messages',
                        'type' => 'array',
                        'desc' => '按时间正序的最近 100 条消息，用户消息只保留原问题',
                        'example' => [[
                            'id' => 1,
                            'role' => 'user',
                            'content' => '我的事业怎么样？',
                            'created_at' => '2026-10-08 15:00:00',
                        ]],
                        'items' => [
                            ['key' => 'id', 'type' => 'integer', 'desc' => '消息 ID', 'example' => 1],
                            ['key' => 'role', 'type' => 'string', 'desc' => 'user 或 assistant', 'example' => 'user'],
                            ['key' => 'content', 'type' => 'string', 'desc' => '消息正文', 'example' => '我的事业怎么样？'],
                            ['key' => 'created_at', 'type' => 'string', 'desc' => '创建时间', 'example' => '2026-10-08 15:00:00'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($rows as $row) {
            SystemApiInterface::query()->updateOrCreate(
                ['method' => 'POST', 'path' => $row['path']],
                [
                    'name' => $row['name'],
                    'module' => 'chatAI',
                    'method' => 'POST',
                    'path' => $row['path'],
                    'request_params' => $row['request_params'],
                    'response_params' => $row['response_params'],
                    'is_enable' => 1,
                    'remark' => $row['remark'],
                ]
            );
        }
    }

    public function down(): void
    {
        SystemApiInterface::query()
            ->where('method', 'POST')
            ->whereIn('path', [
                'chatAI/wenmo/message',
                'chatAI/wenmo/sessions',
                'chatAI/wenmo/history',
            ])
            ->delete();
    }
};
