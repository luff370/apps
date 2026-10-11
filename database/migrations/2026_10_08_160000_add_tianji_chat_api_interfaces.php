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
            'desc' => '由智能体声明的额外参数示例。紫微排盘用它传本地命盘报告，其它智能体按自己的声明传',
            'example' => ['命宫' => '紫微', '官禄宫' => '武曲'],
        ];
        $sections = [
            'key' => 'sections',
            'type' => 'object',
            'desc' => '按标题拆开的片段。简短回答不使用这些标题，对应值为空字符串，客户端展示 result',
            'example' => ['已知资料' => '', '计算结果' => '', '知识依据' => '', '综合解读' => '', '行动建议' => ''],
        ];

        $rows = [
            [
                'name' => '文墨对话',
                'path' => 'chatAI/wenmo/message',
                'remark' => '流式 SSE。用 agent_key 指定智能体；额外字段由该智能体声明。事件为 meta、delta、replace、done、error',
                'request_params' => [
                    ['key' => 'session_id', 'type' => 'integer', 'required' => false, 'desc' => '已有对话 ID。传入后忽略自动续接', 'example' => 1],
                    ['key' => 'new_session', 'type' => 'boolean', 'required' => false, 'desc' => '为 true 时新开对话，不续接同一智能体的最近会话', 'example' => false],
                    ['key' => 'agent_key', 'type' => 'string', 'required' => false, 'desc' => '智能体标识。新建时必填，续接已有对话时可省略', 'example' => 'ziwei'],
                    ['key' => 'content', 'type' => 'string', 'required' => true, 'desc' => '用户问题，最多 2000 字', 'example' => '我的事业怎么样？'],
                    $report,
                ],
                'response_params' => [
                    ['key' => 'session_id', 'type' => 'integer', 'desc' => '对话 ID，后续续接时回传', 'example' => 1],
                    ['key' => 'message_id', 'type' => 'integer', 'desc' => '本轮助手消息 ID', 'example' => 2],
                    ['key' => 'agent_key', 'type' => 'string', 'desc' => '智能体标识', 'example' => 'ziwei'],
                    ['key' => 'result', 'type' => 'string', 'desc' => '完整回答，以免责声明结尾', 'example' => "官禄宫见武曲，事业宜按已有安排推进。\n说明：内容仅供传统文化研究和自我观察参考"],
                    $sections,
                    ['key' => 'valid', 'type' => 'boolean', 'desc' => '是否带有免责声明', 'example' => true],
                    ['key' => 'missing', 'type' => 'array', 'desc' => '仍缺失的校验项，通过时为空数组', 'example' => []],
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
                        'desc' => '最近 20 条对话摘要，按更新时间倒序。同一智能体可以有多条；不传 session_id 时续接最近一条',
                        'example' => [[
                            'id' => 1,
                            'agent_key' => 'ziwei',
                            'title' => '我的事业怎么样',
                            'has_report' => true,
                            'updated_at' => '2026-10-11 15:00:00',
                        ]],
                        'items' => [
                            ['key' => 'id', 'type' => 'integer', 'desc' => '对话 ID', 'example' => 1],
                            ['key' => 'agent_key', 'type' => 'string', 'desc' => '智能体标识', 'example' => 'ziwei'],
                            ['key' => 'title', 'type' => 'string', 'desc' => '首问摘要', 'example' => '我的事业怎么样'],
                            ['key' => 'has_report', 'type' => 'boolean', 'desc' => '是否已保存排盘报告', 'example' => true],
                            ['key' => 'updated_at', 'type' => 'string', 'desc' => '最近更新时间', 'example' => '2026-10-11 15:00:00'],
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
                            'agent_key' => 'ziwei',
                            'title' => '我的事业怎么样',
                            'has_report' => true,
                            'report' => ['命宫' => '紫微', '官禄宫' => '武曲'],
                            'inputs' => ['report' => ['命宫' => '紫微', '官禄宫' => '武曲']],
                            'updated_at' => '2026-10-11 15:00:00',
                        ],
                        'properties' => [
                            ['key' => 'id', 'type' => 'integer', 'desc' => '对话 ID', 'example' => 1],
                            ['key' => 'agent_key', 'type' => 'string', 'desc' => '智能体标识', 'example' => 'ziwei'],
                            ['key' => 'title', 'type' => 'string', 'desc' => '首问摘要', 'example' => '我的事业怎么样'],
                            ['key' => 'has_report', 'type' => 'boolean', 'desc' => '是否已保存排盘报告', 'example' => true],
                            ['key' => 'report', 'type' => 'object', 'desc' => '已保存的排盘报告', 'example' => ['命宫' => '紫微']],
                            ['key' => 'inputs', 'type' => 'object', 'desc' => '该智能体声明并已保存的请求参数', 'example' => ['report' => ['命宫' => '紫微']]],
                            ['key' => 'updated_at', 'type' => 'string', 'desc' => '最近更新时间', 'example' => '2026-10-11 15:00:00'],
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
