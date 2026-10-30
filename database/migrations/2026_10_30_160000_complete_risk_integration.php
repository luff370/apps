<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scopes', function (Blueprint $t) {
            $t->string('basis_hash', 64)->nullable();
            $t->dateTime('hold_at')->nullable();
            $t->string('identity_kind', 16)->default('legacy');
        });
        Schema::table('risk_activity_events', function (Blueprint $t) {
            $t->boolean('online_verified')->default(false);
            $t->unsignedBigInteger('monotonic_elapsed_ms')->nullable();
            $t->string('event_type', 24)->default('activity');
            $t->string('decision', 16)->nullable();
            $t->index(['app_id', 'device_identity', 'session_id', 'event_seq'], 'risk_activity_session_seq');
        });
        Schema::table('risk_timeline', fn (Blueprint $t) => $t->unsignedBigInteger('activity_event_id')->nullable()->unique());
        Schema::create('risk_access_facts', function (Blueprint $t) {
            $t->id();
            $t->string('event_key', 64)->unique();
            $t->string('phone', 100)->nullable();
            $t->unsignedInteger('app_id');
            $t->string('device_identity', 255);
            $t->string('user_id', 64)->nullable();
            $t->string('account', 100)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('version_name', 64)->nullable();
            $t->string('market_channel', 64)->nullable();
            $t->string('event_type', 24);
            $t->string('decision', 16);
            $t->dateTime('occurred_at');
            $t->dateTime('received_at');
            $t->index(['occurred_at', 'app_id']);
        });
        Schema::create('risk_review_history', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('app_id');
            $t->string('attempt_key', 64);
            $t->string('version_name', 64);
            $t->string('market_channel', 64);
            $t->dateTime('submitted_at')->nullable();
            $t->dateTime('listed_at')->nullable();
            $t->string('status', 24);
            $t->dateTime('effective_at');
            $t->index(['app_id', 'version_name', 'market_channel', 'effective_at'], 'risk_review_lookup');
            $t->index(['attempt_key', 'id']);
        });
        Schema::create('risk_hard_blocks', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('app_id');
            $t->string('device_identity', 255);
            $t->string('kind', 32);
            $t->string('evidence_key', 64)->unique();
            $t->json('evidence');
            $t->dateTime('occurred_at');
            $t->dateTime('invalidated_at')->nullable();
            $t->index(['app_id', 'device_identity', 'invalidated_at'], 'risk_hard_block_lookup');
        });
        Schema::create('risk_scope_accounts', function (Blueprint $t) {
            $t->unsignedBigInteger('scope_id');
            $t->unsignedInteger('app_id');
            $t->string('user_id', 64);
            $t->dateTime('first_at');
            $t->dateTime('last_at');
            $t->primary(['scope_id', 'user_id']);
            $t->index(['app_id', 'first_at']);
        });
        Schema::create('risk_reason_evidence', function (Blueprint $t) {
            $t->unsignedBigInteger('scope_id');
            $t->string('code', 64);
            $t->string('ip', 45);
            $t->unsignedInteger('app_id');
            $t->string('device_identity', 255);
            $t->unsignedInteger('hit_count');
            $t->dateTime('first_at');
            $t->dateTime('last_at');
            $t->primary(['scope_id', 'code']);
            $t->index(['ip', 'code']);
        });
        Schema::create('risk_ip_locks', function (Blueprint $t) {
            $t->string('ip', 45)->primary();
        });
        Schema::table('risk_monitor_accounts', fn (Blueprint $t) => $t->unsignedInteger('app_id')->default(0));
        Schema::table('risk_monitor_accounts', function (Blueprint $t) {
            $t->dropUnique(['account', 'subject_name']);
            $t->unique(['account', 'app_id', 'subject_name'], 'risk_monitor_account_scope');
        });
        Schema::table('risk_ledger_meta', function (Blueprint $t) {
            $t->dateTime('coverage_started_at')->nullable();
            $t->dateTime('last_success_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('risk_ledger_meta', fn (Blueprint $t) => $t->dropColumn(['coverage_started_at', 'last_success_at']));
        Schema::table('risk_monitor_accounts', function (Blueprint $t) {
            $t->dropUnique('risk_monitor_account_scope');
            $t->dropColumn('app_id');
            $t->unique(['account', 'subject_name']);
        });
        Schema::dropIfExists('risk_ip_locks');
        Schema::dropIfExists('risk_access_facts');
        Schema::dropIfExists('risk_reason_evidence');
        Schema::dropIfExists('risk_scope_accounts');
        Schema::dropIfExists('risk_hard_blocks');
        Schema::dropIfExists('risk_review_history');
        Schema::table('risk_timeline', function (Blueprint $t) {
            $t->dropUnique(['activity_event_id']);
            $t->dropColumn('activity_event_id');
        });
        Schema::table('risk_activity_events', function (Blueprint $t) {
            $t->dropIndex('risk_activity_session_seq');
            $t->dropColumn(['online_verified', 'monotonic_elapsed_ms', 'event_type', 'decision']);
        });
        Schema::table('risk_scopes', fn (Blueprint $t) => $t->dropColumn(['basis_hash', 'hold_at', 'identity_kind']));
    }
};
