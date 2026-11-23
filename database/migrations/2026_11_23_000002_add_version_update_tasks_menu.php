<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const AUTH = 'admin-app-version-update-tasks';

    public function up(): void
    {
        $parent = DB::table('system_menus')->where('unique_auth', 'admin-app-apps')->where('is_del', 0)->first();
        if (!$parent) {
            return;
        }

        DB::transaction(function () use ($parent) {
            DB::table('system_menus')->updateOrInsert(['unique_auth' => self::AUTH], [
                'pid' => $parent->id,
                'path' => trim($parent->path . '/' . $parent->id, '/'),
                'icon' => '',
                'menu_name' => '版本更新任务',
                'menu_path' => '/admin/app/apps/version_tasks',
                'module' => 'admin',
                'controller' => '',
                'action' => '',
                'api_url' => '',
                'methods' => 'GET',
                'params' => '[]',
                'sort' => 98,
                'is_show' => 1,
                'is_show_path' => 0,
                'access' => 1,
                'auth_type' => 1,
                'header' => 'app',
                'is_header' => 0,
                'is_del' => 0,
            ]);
            $menuId = (int)DB::table('system_menus')->where('unique_auth', self::AUTH)->value('id');
            $this->updateRoleRules($menuId, (int)$parent->id);
        });

        Cache::forget('all_auth');
    }

    public function down(): void
    {
        $menuId = (int)DB::table('system_menus')->where('unique_auth', self::AUTH)->value('id');
        if (!$menuId) {
            return;
        }
        DB::transaction(function () use ($menuId) {
            $this->updateRoleRules($menuId);
            DB::table('system_menus')->where('id', $menuId)->delete();
        });
        Cache::forget('all_auth');
    }

    private function updateRoleRules(int $menuId, int $parentId = 0): void
    {
        foreach (DB::table('system_role')->get(['id', 'rules']) as $role) {
            $rules = array_map('intval', array_filter(explode(',', (string)$role->rules)));
            $next = $parentId
                ? (in_array($parentId, $rules, true) ? array_unique([...$rules, $menuId]) : $rules)
                : array_diff($rules, [$menuId]);
            if ($rules !== $next) {
                DB::table('system_role')->where('id', $role->id)->update(['rules' => implode(',', $next)]);
            }
        }
    }
};
