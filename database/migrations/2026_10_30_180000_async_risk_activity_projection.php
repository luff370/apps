<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::table('risk_activity_events', function (Blueprint $table) {
            $table->dateTime('projected_at')->nullable();
            $table->index(['projected_at', 'id'], 'risk_activity_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_jobs');
        Schema::table('risk_activity_events', function (Blueprint $table) {
            $table->dropIndex('risk_activity_pending');
            $table->dropColumn('projected_at');
        });
    }
};
