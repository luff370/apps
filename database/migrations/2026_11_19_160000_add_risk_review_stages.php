<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scopes', function (Blueprint $table) {
            $table->string('review_stage', 16)->default('enforced');
            $table->json('review_reasons')->nullable();
            $table->dateTime('reviewed_at')->nullable();
        });
        Schema::table('risk_ip_dossiers', fn (Blueprint $table) => $table->unsignedInteger('resource_version')->default(1));
        Schema::create('risk_review_actions', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->unique();
            $table->string('ip', 45)->index();
            $table->string('from_state', 16);
            $table->string('to_state', 16);
            $table->unsignedInteger('operator_id')->default(0);
            $table->string('operator_name', 100);
            $table->json('before_scopes');
            $table->json('result');
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_review_actions');
        Schema::table('risk_ip_dossiers', fn (Blueprint $table) => $table->dropColumn('resource_version'));
        Schema::table('risk_scopes', fn (Blueprint $table) => $table->dropColumn(['review_stage', 'review_reasons', 'reviewed_at']));
    }
};
