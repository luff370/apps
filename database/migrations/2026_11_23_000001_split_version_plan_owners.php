<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->string('developer_name', 80)->default('')->index();
            $table->string('operator_name', 80)->default('')->index();
            $table->boolean('remark_is_html')->default(false);
        });

        DB::table('app_version_plans')->where('owner_name', '<>', '')->update([
            'developer_name' => DB::raw('owner_name'),
            'operator_name' => DB::raw('owner_name'),
        ]);
    }

    public function down(): void
    {
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->dropIndex(['developer_name']);
            $table->dropIndex(['operator_name']);
        });
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->dropColumn(['developer_name', 'operator_name', 'remark_is_html']);
        });
    }
};
