<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('system_menus')->where('unique_auth', 'user-deletion-request')->exists()) {
            return;
        }

        $parent = DB::table('system_menus')
            ->where('menu_path', '/admin/user')
            ->where('pid', 0)
            ->where('is_del', 0)
            ->first();
        $pid = $parent->id ?? 9;

        DB::table('system_menus')->insert([
            'pid' => $pid,
            'icon' => 'ios-trash',
            'menu_name' => '账号删除申请',
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => '',
            'params' => '[]',
            'sort' => 3,
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'menu_path' => '/admin/user/deletion_request',
            'path' => (string) $pid,
            'auth_type' => 1,
            'header' => '',
            'is_header' => 0,
            'unique_auth' => 'user-deletion-request',
            'is_del' => 0,
        ]);
    }

    public function down(): void
    {
        DB::table('system_menus')->where('unique_auth', 'user-deletion-request')->delete();
    }
};
