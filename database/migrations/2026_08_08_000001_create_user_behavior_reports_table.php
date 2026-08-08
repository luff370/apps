<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_behavior_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->default(0)->index();
            $table->unsignedBigInteger('app_id')->default(0)->index();
            $table->string('uuid', 100)->default('')->index();
            $table->string('device_sn', 255)->default('')->index()->comment('设备码，Device-Sn 缺失时使用 Uuid');
            $table->string('package_name', 190)->default('');
            $table->string('platform', 20)->default('');
            $table->string('app_version', 50)->default('');
            $table->string('market_channel', 100)->default('');
            $table->string('ip', 45)->default('');
            $table->json('behavior');
            $table->json('device_environment')->nullable();
            $table->json('ad_extension')->nullable();
            $table->timestamp('client_reported_at')->nullable()->index();
            $table->timestamps();
            $table->index(['app_id', 'created_at']);
            $table->index(['device_sn', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behavior_reports');
    }
};
