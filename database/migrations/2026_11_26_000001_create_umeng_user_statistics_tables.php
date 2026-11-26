<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umeng_user_uuids', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id');
            $table->string('uuid', 64);
            $table->string('market_channel', 32)->default('');
            $table->string('version', 32)->default('');
            $table->string('oaid', 128)->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'uuid']);
            $table->index(['created_at', 'app_id', 'market_channel'], 'umeng_devices_date_app_channel_idx');
        });

        Schema::create('umeng_user_activity', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedInteger('app_id');
            $table->string('uuid', 64);
            $table->string('market_channel', 32)->default('');

            $table->primary(['date', 'app_id', 'market_channel', 'uuid'], 'umeng_activity_device_day_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umeng_user_activity');
        Schema::dropIfExists('umeng_user_uuids');
    }
};
