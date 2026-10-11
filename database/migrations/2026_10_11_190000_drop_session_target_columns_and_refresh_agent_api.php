<?php

use App\Models\AppApiObfuscationAlias;
use App\Models\SystemApiInterface;
use App\Services\App\AppApiObfuscationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropTargetColumns();
        $this->refreshApiCatalog();
    }

    public function down(): void
    {
        if (!Schema::hasTable('tianji_chat_sessions') || Schema::hasColumn('tianji_chat_sessions', 'target_type')) {
            return;
        }

        Schema::table('tianji_chat_sessions', function (Blueprint $table) {
            $table->dropIndex('tianji_chat_sessions_agent_index');
            $table->string('target_type', 16)->default('agent')->after('user_id')->comment('skill 或 data');
            $table->string('target_key', 64)->default('')->after('target_type')->comment('技能标识或资料标识');
            $table->index(['user_id', 'app_id', 'target_type', 'target_key'], 'tianji_chat_sessions_target_index');
        });
    }

    /** 对话只按智能体区分，去掉已经不再接收的 target_type、target_key。 */
    private function dropTargetColumns(): void
    {
        if (!Schema::hasTable('tianji_chat_sessions') || !Schema::hasColumn('tianji_chat_sessions', 'target_type')) {
            return;
        }

        Schema::table('tianji_chat_sessions', function (Blueprint $table) {
            $table->dropIndex('tianji_chat_sessions_target_index');
            $table->dropColumn(['target_type', 'target_key']);
            $table->index(['user_id', 'app_id', 'agent_key'], 'tianji_chat_sessions_agent_index');
        });
    }

    /** 公共 API 改为智能体路径，请求和响应存真实 JSON 样例。 */
    private function refreshApiCatalog(): void
    {
        if (!Schema::hasTable('system_api_interfaces')) {
            return;
        }

        $this->saveInterface('chatAI/wenmo/message', 'chatAI/agent/message', '智能体对话', '流式 SSE。首轮传 agent_key、content 和额外字段。同对话后续可只传 agent_key 与 content，或 session_id 与 content。new_session 不传等于 false', [
            'agent_key' => 'ziwei',
            'content' => '我的事业怎么样？',
            'session_id' => 1,
            'new_session' => false,
            'report' => [
                '命宫' => '紫微',
                '官禄宫' => '武曲',
                '财帛宫' => '天府',
            ],
        ], [
            'session_id' => 1,
            'message_id' => 2,
            'agent_key' => 'ziwei',
            'result' => "官禄宫见武曲，事业宜按已有安排推进。\n说明：内容仅供传统文化研究和自我观察参考",
            'sections' => [
                '已知资料' => '',
                '计算结果' => '',
                '知识依据' => '',
                '综合解读' => '',
                '行动建议' => '',
            ],
            'valid' => true,
            'missing' => [],
            'corrected' => false,
        ]);

        $this->saveInterface('chatAI/wenmo/sessions', 'chatAI/agent/sessions', '智能体对话列表', '当前用户最近 20 条智能体对话', [], [
            'list' => [[
                'id' => 1,
                'agent_key' => 'ziwei',
                'title' => '我的事业怎么样',
                'has_report' => true,
                'updated_at' => '2026-10-11 15:00:00',
            ]],
        ]);

        $this->saveInterface('chatAI/wenmo/history', 'chatAI/agent/history', '智能体对话记录', 'inputs 是已保存的额外参数。紫微的 report 会同时出现在 session.report，客户端读命盘用 session.report', [
            'session_id' => 1,
        ], [
            'session' => [
                'id' => 1,
                'agent_key' => 'ziwei',
                'title' => '我的事业怎么样',
                'has_report' => true,
                'report' => [
                    '命宫' => '紫微',
                    '官禄宫' => '武曲',
                    '财帛宫' => '天府',
                ],
                'inputs' => [
                    'report' => [
                        '命宫' => '紫微',
                        '官禄宫' => '武曲',
                        '财帛宫' => '天府',
                    ],
                ],
                'updated_at' => '2026-10-11 15:00:00',
            ],
            'messages' => [
                [
                    'id' => 1,
                    'role' => 'user',
                    'content' => '我的事业怎么样？',
                    'created_at' => '2026-10-11 15:00:00',
                ],
                [
                    'id' => 2,
                    'role' => 'assistant',
                    'content' => "官禄宫见武曲，事业宜按已有安排推进。\n说明：内容仅供传统文化研究和自我观察参考",
                    'created_at' => '2026-10-11 15:00:01',
                ],
            ],
        ]);

        $this->refreshAliases();
    }

    /** 沿用原来的公共 API 记录，只改路径、名称和真实 JSON 样例。 */
    private function saveInterface(string $from, string $to, string $name, string $remark, array $request, array $response): void
    {
        $row = SystemApiInterface::query()->where('method', 'POST')->where('path', $from)->first()
            ?? SystemApiInterface::query()->where('method', 'POST')->where('path', $to)->first();
        if (!$row) {
            return;
        }

        $row->name = $name;
        $row->path = $to;
        $row->module = 'chatAI';
        $row->remark = $remark;
        $row->request_params = $request;
        $row->response_params = $response;
        $row->save();
    }

    /** 已有接口别名按新样例补字段映射。同一字段的别名不变，新增的嵌套字段会补上。 */
    private function refreshAliases(): void
    {
        if (!Schema::hasTable('app_api_obfuscation_aliases')) {
            return;
        }

        $interfaceIds = SystemApiInterface::query()
            ->whereIn('path', ['chatAI/agent/message', 'chatAI/agent/sessions', 'chatAI/agent/history'])
            ->pluck('id');
        if ($interfaceIds->isEmpty()) {
            return;
        }

        $service = app(AppApiObfuscationService::class);
        foreach (AppApiObfuscationAlias::query()->whereIn('interface_id', $interfaceIds)->get() as $alias) {
            $service->syncAliasParams((int) $alias->id);
            $maps = $service->generateAliasParams(['id' => (int) $alias->id]);
            if ($maps === []) {
                continue;
            }
            $alias->request_key_map = $maps['request_key_map'] ?? [];
            $alias->response_key_map = $maps['response_key_map'] ?? [];
            $alias->save();
        }
    }
};
