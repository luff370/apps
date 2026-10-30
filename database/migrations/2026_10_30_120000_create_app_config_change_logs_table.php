<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_config_change_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedInteger('app_id');
            $table->unsignedInteger('previous_app_id')->nullable();
            $table->string('channel', 32);
            $table->string('key', 32);
            $table->string('action', 16);
            $table->string('source', 32);
            $table->unsignedInteger('admin_id')->default(0);
            $table->string('admin_account', 128)->default('');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['app_id', 'id']);
            $table->index(['previous_app_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_config_change_logs');
    }
};
