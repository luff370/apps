<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('system_menus')->where('unique_auth', 'app-domain')->exists()) {
            return;
        }

        $parent = DB::table('system_menus')
            ->where('menu_path', '/admin/app')
            ->where('pid', 0)
            ->where('is_del', 0)
            ->first();
        $pid = $parent->id ?? 135;

        DB::table('system_menus')->insert([
            'pid' => $pid,
            'icon' => 'ios-globe',
            'menu_name' => '域名管理',
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => '',
            'params' => '[]',
            'sort' => 89,
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'menu_path' => '/admin/app/domain',
            'path' => (string) $pid,
            'auth_type' => 1,
            'header' => '',
            'is_header' => 0,
            'unique_auth' => 'app-domain',
            'is_del' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_menus')->where('unique_auth', 'app-domain')->delete();
    }
};
