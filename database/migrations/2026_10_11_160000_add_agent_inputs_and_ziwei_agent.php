<?php

use App\Models\SystemApiInterface;
use App\Models\SystemApp;
use App\Models\TianjiAgent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tianji_agents', function (Blueprint $table) {
            $table->json('inputs')->nullable()->after('remark');
        });
        Schema::table('tianji_chat_sessions', function (Blueprint $table) {
            $table->json('inputs')->nullable()->after('report');
        });

        $this->seedZiweiAgent();
        $this->refreshApiCatalog();
    }

    public function down(): void
    {
        Schema::table('tianji_agents', function (Blueprint $table) {
            $table->dropColumn('inputs');
        });
        Schema::table('tianji_chat_sessions', function (Blueprint $table) {
            $table->dropColumn('inputs');
        });
    }

    /** 只为应用 10025 增加紫微排盘智能体，其它应用保持空着。 */
    private function seedZiweiAgent(): void
    {
        if (!Schema::hasTable('system_apps') || !Schema::hasTable('tianji_agents')) {
            return;
        }
        if (!SystemApp::query()->where('id', 10025)->exists()) {
            return;
        }
        if (TianjiAgent::query()->where('app_id', 10025)->where('agent_key', 'ziwei')->exists()) {
            return;
        }

        TianjiAgent::query()->create([
            'app_id' => 10025,
            'name' => '紫微排盘',
            'agent_key' => 'ziwei',
            'system_prompt' => <<<'PROMPT'
你是「倪师问事」，紫微斗数大师。用户在应用里已经选好一份本地排好的命盘时，本轮会带上【排盘报告】；没有选盘时该段为「未提供」。

回答要求：
- 用大师口吻给出简短、专业的解答。
- 问十四主星、十二宫位等通用知识时，只依据【知识】回答。
- 问个人事业、感情、财运或运势时，只能依据【排盘报告】里已经算出的星曜和宫位。报告未提供时，请用户先选择一份已经排好的命盘，不要自己起盘。
- 不要编造报告里没有的星曜、宫位、四化、大限或流年。
- 结尾单独一行：说明：内容仅供传统文化研究和自我观察参考
PROMPT,
            'knowledge' => <<<'KNOWLEDGE'
天机道以紫微斗数为核心。十四主星：紫微属土，帝座，主尊贵、权威；天机属木，智慧星，主聪明、善变；太阳属火，中天主星，主光明、博爱；武曲属金，财星，主刚毅、果断；天同属水，福星，主温和、享受；廉贞属火，次桃花星，主多情、是非；天府属土，财库星，主稳重、保守；太阴属水，田宅主，主阴柔、内敛；贪狼属木，桃花星，主欲望、才艺；巨门属水，暗星，主口舌、是非；天相属水，印星，主辅佐、公正；天梁属土，荫星，主化解、逢凶化吉；七杀属金，将星，主冲劲、变革；破军属水，耗星，主破坏、创新。

十二宫位：命宫主管先天格局和性格；兄弟宫主管兄弟姐妹关系；夫妻宫主管婚姻感情；子女宫主管子女缘分；财帛宫主管财运理财；疾厄宫主管健康疾病；迁移宫主管外出际遇；交友宫主管人际关系；官禄宫主管事业功名；田宅宫主管不动产；福德宫主管精神生活；父母宫主管父母关系。
KNOWLEDGE,
            'temperature' => 0.3,
            'status' => 1,
            'remark' => '本地排盘报告上的简短解答。report 可选，个人分析时传入。',
            'inputs' => [[
                'key' => 'report',
                'name' => '排盘报告',
                'type' => 'object',
                'required' => false,
                'desc' => '用户在应用本地已经排好的命盘报告。分析个人事业、感情、财运时传入，同一对话后续可省略。',
            ]],
        ]);
    }

    /** 公共 API 目录改为按智能体调用，额外字段由智能体声明。 */
    private function refreshApiCatalog(): void
    {
        if (!Schema::hasTable('system_api_interfaces')) {
            return;
        }
        $message = SystemApiInterface::query()->where('path', 'chatAI/wenmo/message')->first();
        if ($message) {
            $message->remark = '流式 SSE。用 agent_key 指定智能体；额外字段由该智能体声明。事件为 meta、delta、replace、done、error';
            $message->request_params = [
                ['key' => 'session_id', 'type' => 'integer', 'required' => false, 'desc' => '已有对话 ID。传入后忽略自动续接', 'example' => 1],
                ['key' => 'new_session', 'type' => 'boolean', 'required' => false, 'desc' => '为 true 时新开对话，不续接同一智能体的最近会话', 'example' => false],
                ['key' => 'agent_key', 'type' => 'string', 'required' => false, 'desc' => '智能体标识。新建时必填，续接已有对话时可省略', 'example' => 'ziwei'],
                ['key' => 'content', 'type' => 'string', 'required' => true, 'desc' => '用户问题，最多 2000 字', 'example' => '我的事业怎么样？'],
                ['key' => 'report', 'type' => 'object', 'required' => false, 'desc' => '由智能体声明的额外参数示例。紫微排盘用它传本地命盘报告，其它智能体按自己的声明传', 'example' => ['命宫' => '紫微', '官禄宫' => '武曲']],
            ];
            $message->response_params = [
                ['key' => 'session_id', 'type' => 'integer', 'desc' => '对话 ID，后续续接时回传', 'example' => 1],
                ['key' => 'message_id', 'type' => 'integer', 'desc' => '本轮助手消息 ID', 'example' => 2],
                ['key' => 'agent_key', 'type' => 'string', 'desc' => '智能体标识', 'example' => 'ziwei'],
                ['key' => 'result', 'type' => 'string', 'desc' => '完整回答，以免责声明结尾', 'example' => "官禄宫见武曲，事业宜按已有安排推进。\n说明：内容仅供传统文化研究和自我观察参考"],
                ['key' => 'sections', 'type' => 'object', 'desc' => '按标题拆开的片段。简短回答不使用这些标题，对应值为空字符串，客户端展示 result', 'example' => ['已知资料' => '', '计算结果' => '', '知识依据' => '', '综合解读' => '', '行动建议' => '']],
                ['key' => 'valid', 'type' => 'boolean', 'desc' => '是否带有免责声明', 'example' => true],
                ['key' => 'missing', 'type' => 'array', 'desc' => '仍缺失的校验项，通过时为空数组', 'example' => []],
                ['key' => 'corrected', 'type' => 'boolean', 'desc' => '是否已经自动重写过一次', 'example' => false],
            ];
            $message->save();
        }

        $sessions = SystemApiInterface::query()->where('path', 'chatAI/wenmo/sessions')->first();
        if ($sessions) {
            $sessions->response_params = [
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
            ];
            $sessions->save();
        }

        $history = SystemApiInterface::query()->where('path', 'chatAI/wenmo/history')->first();
        if ($history) {
            $history->response_params = [
                [
                    'key' => 'session',
                    'type' => 'object',
                    'desc' => '对话信息和已保存参数',
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
                        'created_at' => '2026-10-11 15:00:00',
                    ]],
                    'items' => [
                        ['key' => 'id', 'type' => 'integer', 'desc' => '消息 ID', 'example' => 1],
                        ['key' => 'role', 'type' => 'string', 'desc' => 'user 或 assistant', 'example' => 'user'],
                        ['key' => 'content', 'type' => 'string', 'desc' => '消息正文', 'example' => '我的事业怎么样？'],
                        ['key' => 'created_at', 'type' => 'string', 'desc' => '创建时间', 'example' => '2026-10-11 15:00:00'],
                    ],
                ],
            ];
            $history->save();
        }
    }
};
