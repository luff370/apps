<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('system_menus')->where('unique_auth', 'data-statistic-user-statistics')->exists()) {
            return;
        }

        $parent = DB::table('system_menus')
            ->where('unique_auth', 'data_statistic')
            ->where('is_del', 0)
            ->first();
        $pid = $parent->id ?? 1147;

        $menuId = DB::table('system_menus')->insertGetId([
            'pid' => $pid,
            'icon' => 'ios-people',
            'menu_name' => '用户统计',
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => 'GET',
            'params' => '[]',
            'sort' => 0,
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'menu_path' => '/admin/data_statistic/user_statistics',
            'path' => (string) $pid,
            'auth_type' => 1,
            'header' => '',
            'is_header' => 0,
            'unique_auth' => 'data-statistic-user-statistics',
            'is_del' => 0,
        ]);

        $grantMenuIds = DB::table('system_menus')
            ->whereIn('unique_auth', [
                'data_statistic',
                'data-statistic-revenue-report',
                'data-statistic-recharge-statistics',
            ])
            ->where('is_del', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!$grantMenuIds) {
            return;
        }

        $roles = DB::table('system_role')->get(['id', 'rules']);
        foreach ($roles as $role) {
            $ruleIds = array_values(array_filter(array_map('intval', explode(',', (string) $role->rules))));
            if (!$ruleIds || !array_intersect($ruleIds, $grantMenuIds)) {
                continue;
            }
            if (in_array($menuId, $ruleIds, true)) {
                continue;
            }
            $ruleIds[] = $menuId;
            DB::table('system_role')->where('id', $role->id)->update([
                'rules' => implode(',', $ruleIds),
            ]);
        }
    }

    public function down(): void
    {
        $menu = DB::table('system_menus')->where('unique_auth', 'data-statistic-user-statistics')->first();
        if (!$menu) {
            return;
        }

        $roles = DB::table('system_role')->get(['id', 'rules']);
        foreach ($roles as $role) {
            $ruleIds = array_values(array_filter(array_map('intval', explode(',', (string) $role->rules))));
            $next = array_values(array_filter($ruleIds, fn ($id) => $id !== (int) $menu->id));
            if ($next === $ruleIds) {
                continue;
            }
            DB::table('system_role')->where('id', $role->id)->update([
                'rules' => implode(',', $next),
            ]);
        }

        DB::table('system_menus')->where('id', $menu->id)->delete();
    }
};
