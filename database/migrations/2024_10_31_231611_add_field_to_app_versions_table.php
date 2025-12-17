<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->comment('应用ID')->after('id');
            $table->string('remark')->default('')->comment('备注信息')->after('audit_status');
            $table->dropColumn('add_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('app_id');
            $table->dropColumn('remark');
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
            $table->unsignedInteger('add_time')->comment('添加时间');
        });
    }
};
