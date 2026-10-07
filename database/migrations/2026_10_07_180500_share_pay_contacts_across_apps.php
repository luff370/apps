<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_pay_contact_apps')) {
            Schema::create('app_pay_contact_apps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contact_id')->comment('联系人ID');
                $table->unsignedInteger('app_id')->comment('应用ID');
                $table->unsignedInteger('assign_count')->default(0)->comment('该应用下已分配次数');
                $table->timestamps();

                $table->unique(['contact_id', 'app_id']);
                $table->index(['app_id', 'assign_count']);
                $table->foreign('contact_id')->references('id')->on('app_pay_contacts')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('app_pay_contacts', 'app_id')) {
            DB::table('app_pay_contacts')
                ->where('app_id', '>', 0)
                ->orderBy('id')
                ->get(['id', 'app_id', 'assign_count'])
                ->each(function ($row) {
                    DB::table('app_pay_contact_apps')->updateOrInsert(
                        ['contact_id' => $row->id, 'app_id' => $row->app_id],
                        ['assign_count' => (int) $row->assign_count, 'updated_at' => now(), 'created_at' => now()]
                    );
                });

            Schema::table('app_pay_contacts', function (Blueprint $table) {
                $table->dropIndex(['app_id', 'is_enable', 'assign_count']);
                $table->dropColumn(['app_id', 'assign_count']);
                $table->index(['is_enable', 'sort']);
            });
        }

        if (!DB::table('system_menus')->where('unique_auth', 'app-pay-contacts')->exists()) {
            $parentId = (int) DB::table('system_menus')->where('unique_auth', 'admin-app')->value('id');
            if ($parentId > 0) {
                $menuId = DB::table('system_menus')->insertGetId([
                    'pid' => $parentId,
                    'icon' => '',
                    'menu_name' => '微信联系人',
                    'module' => 'admin',
                    'controller' => '',
                    'action' => '',
                    'api_url' => '',
                    'methods' => '',
                    'params' => '[]',
                    'sort' => 5,
                    'is_show' => 1,
                    'is_show_path' => 0,
                    'access' => 1,
                    'menu_path' => '/admin/app/pay_contacts',
                    'path' => (string) $parentId,
                    'auth_type' => 1,
                    'header' => '',
                    'is_header' => 0,
                    'unique_auth' => 'app-pay-contacts',
                    'is_del' => 0,
                ]);

                $paymentMenuId = (int) DB::table('system_menus')->where('unique_auth', 'app-payments')->value('id');
                $roles = DB::table('system_role')->get(['id', 'rules']);
                foreach ($roles as $role) {
                    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $role->rules))));
                    if (!in_array($parentId, $ids, true) && !in_array($paymentMenuId, $ids, true)) {
                        continue;
                    }
                    $ids[] = $menuId;
                    DB::table('system_role')->where('id', $role->id)->update([
                        'rules' => implode(',', array_values(array_unique($ids))),
                    ]);
                }
                Cache::forget('all_auth');
            }
        }
    }

    public function down(): void
    {
        DB::table('system_menus')->where('unique_auth', 'app-pay-contacts')->delete();
        Cache::forget('all_auth');
        Schema::dropIfExists('app_pay_contact_apps');
    }
};
