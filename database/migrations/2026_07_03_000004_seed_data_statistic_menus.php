<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedDataStatisticMenus extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('system_menus')->where('unique_auth', 'data_statistic')->value('id');

        if (!$parentId) {
            $parentId = DB::table('system_menus')->insertGetId([
                'pid' => 0,
                'icon' => 'ios-stats',
                'menu_name' => '数据统计',
                'module' => 'admin',
                'controller' => '',
                'action' => '',
                'api_url' => '',
                'methods' => '',
                'params' => '',
                'sort' => 4,
                'is_show' => 1,
                'is_show_path' => 0,
                'access' => 1,
                'menu_path' => '/admin/data_statistic',
                'path' => '',
                'auth_type' => 1,
                'header' => '',
                'is_header' => 0,
                'unique_auth' => 'data_statistic',
                'is_del' => 0,
            ]);
        }

        $this->upsertMenu('data-statistic-revenue-report', [
            'pid' => $parentId,
            'icon' => '',
            'menu_name' => '营收报表',
            'menu_path' => '/admin/data_statistic/revenue_report',
            'path' => (string)$parentId,
            'sort' => 2,
        ]);

        $this->upsertMenu('data-statistic-recharge-statistics', [
            'pid' => $parentId,
            'icon' => '',
            'menu_name' => '充值统计',
            'menu_path' => '/admin/data_statistic/recharge_statistics',
            'path' => (string)$parentId,
            'sort' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('system_menus')
            ->whereIn('unique_auth', [
                'data-statistic-revenue-report',
                'data-statistic-recharge-statistics',
            ])
            ->delete();
    }

    private function upsertMenu(string $uniqueAuth, array $data): void
    {
        $base = [
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => 'GET',
            'params' => '',
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'auth_type' => 1,
            'header' => '',
            'is_header' => 0,
            'is_del' => 0,
        ];

        $exists = DB::table('system_menus')->where('unique_auth', $uniqueAuth)->exists();
        if ($exists) {
            DB::table('system_menus')->where('unique_auth', $uniqueAuth)->update(array_merge($base, $data));
            return;
        }

        DB::table('system_menus')->insert(array_merge($base, $data, ['unique_auth' => $uniqueAuth]));
    }
}
