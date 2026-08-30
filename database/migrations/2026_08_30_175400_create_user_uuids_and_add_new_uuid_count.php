<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_uuids', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('uuid', 64)->comment('客户端生成的唯一用户ID');
            $table->string('market_channel', 32)->default('')->comment('首次出现时的应用市场');
            $table->timestamps();

            $table->unique(['app_id', 'uuid']);
            $table->index(['app_id', 'created_at']);
            $table->index(['app_id', 'market_channel', 'created_at']);
        });

        Schema::table('user_statistics', function (Blueprint $table) {
            $table->unsignedInteger('new_uuid_count')->default(0)->comment('新增UUID数量')->after('new_users_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_statistics', function (Blueprint $table) {
            $table->dropColumn('new_uuid_count');
        });
        Schema::dropIfExists('user_uuids');
    }
};
