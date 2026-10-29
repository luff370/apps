<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('system_menus')
            ->where('unique_auth', 'admin-risk')
            ->where('is_del', 0)
            ->first();
        if (!$parent) {
            return;
        }
        DB::table('system_menus')->where('unique_auth', 'admin-risk-overview')->update(['sort' => 12]);
        DB::table('system_menus')->where('unique_auth', 'admin-risk-list')->update([
            'menu_name' => '风控名单',
            'sort' => 10,
        ]);

        $menus = [
            ['风控概率', '/admin/risk/probability', 'admin-risk-probability', 11],
            ['释放记录', '/admin/risk/releases', 'admin-risk-releases', 9],
            ['上架监控', '/admin/risk/publishing-monitor', 'admin-risk-publishing-monitor', 8],
        ];
        $newIds = [];
        foreach ($menus as [$name, $path, $auth, $sort]) {
            if (DB::table('system_menus')->where('unique_auth', $auth)->exists()) {
                continue;
            }
            $newIds[] = DB::table('system_menus')->insertGetId([
                'pid' => $parent->id,
                'icon' => '',
                'menu_name' => $name,
                'module' => 'admin',
                'controller' => '',
                'action' => '',
                'api_url' => '',
                'methods' => 'GET',
                'params' => '[]',
                'sort' => $sort,
                'is_show' => 1,
                'is_show_path' => 0,
                'access' => 1,
                'menu_path' => $path,
                'path' => (string) $parent->id,
                'auth_type' => 1,
                'header' => 'risk',
                'is_header' => 0,
                'unique_auth' => $auth,
                'is_del' => 0,
            ]);
        }

        $grantIds = DB::table('system_menus')
            ->whereIn('unique_auth', ['admin-risk', 'admin-risk-overview', 'admin-risk-list'])
            ->where('is_del', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($newIds === [] || $grantIds === []) {
            return;
        }
        foreach (DB::table('system_role')->get(['id', 'rules']) as $role) {
            $ruleIds = array_values(array_filter(array_map('intval', explode(',', (string) $role->rules))));
            if (!$ruleIds || !array_intersect($ruleIds, $grantIds)) {
                continue;
            }
            $next = array_values(array_unique(array_merge($ruleIds, $newIds)));
            if ($next === $ruleIds) {
                continue;
            }
            DB::table('system_role')->where('id', $role->id)->update(['rules' => implode(',', $next)]);
        }
    }

    public function down(): void
    {
        $menus = DB::table('system_menus')
            ->whereIn('unique_auth', ['admin-risk-probability', 'admin-risk-releases', 'admin-risk-publishing-monitor'])
            ->get();
        foreach (DB::table('system_role')->get(['id', 'rules']) as $role) {
            $ruleIds = array_values(array_filter(array_map('intval', explode(',', (string) $role->rules))));
            $drop = $menus->pluck('id')->map(fn ($id) => (int) $id)->all();
            $next = array_values(array_filter($ruleIds, fn ($id) => !in_array($id, $drop, true)));
            if ($next !== $ruleIds) {
                DB::table('system_role')->where('id', $role->id)->update(['rules' => implode(',', $next)]);
            }
        }
        DB::table('system_menus')->whereIn('id', $menus->pluck('id'))->delete();
        DB::table('system_menus')->where('unique_auth', 'admin-risk-list')->update(['menu_name' => '名单管理', 'sort' => 1]);
        DB::table('system_menus')->where('unique_auth', 'admin-risk-overview')->update(['sort' => 6]);
    }
};
