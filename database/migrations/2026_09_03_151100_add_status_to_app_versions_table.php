<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(1)->comment('状态 1开启 0关闭')->after('is_new');
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
