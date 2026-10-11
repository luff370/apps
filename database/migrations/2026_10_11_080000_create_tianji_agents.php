<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tianji_agents')) {
            Schema::create('tianji_agents', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('app_id')->comment('应用ID');
                $table->string('name', 32)->comment('显示名称');
                $table->string('agent_key', 64)->comment('客户端 skill 的 target_key');
                $table->mediumText('system_prompt')->comment('系统提示词');
                $table->mediumText('knowledge')->comment('知识内容');
                $table->decimal('temperature', 3, 2)->nullable()->comment('为空时使用渠道默认温度');
                $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
                $table->string('remark', 255)->default('')->comment('备注');
                $table->timestamps();

                $table->unique(['app_id', 'agent_key'], 'tianji_agents_app_key_unique');
            });
        }

        $this->addMenu();
    }

    public function down(): void
    {
        $menuId = DB::table('system_menus')->where('unique_auth', 'cms-agent')->value('id');
        if ($menuId) {
            $roles = DB::table('system_role')->get(['id', 'rules']);
            foreach ($roles as $role) {
                $ids = array_values(array_filter(explode(',', (string) $role->rules), fn ($id) => $id !== (string) $menuId));
                DB::table('system_role')->where('id', $role->id)->update([
                    'rules' => implode(',', $ids),
                ]);
            }
        }
        DB::table('system_menus')->where('unique_auth', 'cms-agent')->delete();
        Schema::dropIfExists('tianji_agents');
    }

    private function addMenu(): void
    {
        if (!Schema::hasTable('system_menus') || DB::table('system_menus')->where('unique_auth', 'cms-agent')->exists()) {
            return;
        }

        $parent = DB::table('system_menus')->where('unique_auth', 'admin-cms')->where('is_del', 0)->first();
        $pid = (int) ($parent->id ?? 0);
        $menuId = DB::table('system_menus')->insertGetId([
            'pid' => $pid,
            'icon' => '',
            'menu_name' => '智能体管理',
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => '',
            'params' => '[]',
            'sort' => 7,
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'menu_path' => '/admin/cms/agent/index',
            'path' => (string) $pid,
            'auth_type' => 1,
            'header' => 'cms',
            'is_header' => 0,
            'unique_auth' => 'cms-agent',
            'is_del' => 0,
        ]);

        if ($pid <= 0 || !Schema::hasTable('system_role')) {
            return;
        }

        $roles = DB::table('system_role')->get(['id', 'rules']);
        foreach ($roles as $role) {
            $ids = array_values(array_filter(explode(',', (string) $role->rules), fn ($id) => $id !== ''));
            if (!in_array((string) $pid, $ids, true) || in_array((string) $menuId, $ids, true)) {
                continue;
            }
            $ids[] = (string) $menuId;
            DB::table('system_role')->where('id', $role->id)->update([
                'rules' => implode(',', $ids),
            ]);
        }
    }

};
