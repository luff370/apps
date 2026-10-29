<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_ledger_meta', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('cursor_id')->default(0);
            $table->timestamps();
        });

        Schema::create('risk_ledger_applied', function (Blueprint $table) {
            $table->unsignedBigInteger('source_log_id')->primary();
            $table->timestamp('applied_at')->useCurrent();
        });

        Schema::create('risk_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->index();
            $table->string('ip', 45)->index();
            $table->string('device_identity', 255);
            $table->string('install_id', 255)->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('market_channel', 64)->nullable();
            $table->string('version_name', 64)->nullable();
            $table->json('reason_codes')->nullable();
            $table->string('risk_level', 16)->default('watch');
            $table->string('decision', 16)->default('verify');
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('state', 16)->default('active')->index();
            $table->dateTime('collected_at');
            $table->dateTime('released_at')->nullable();
            $table->unsignedBigInteger('release_id')->nullable()->index();
            $table->unsignedBigInteger('source_log_id')->nullable();
            $table->string('auto_release_hold_version', 32)->nullable();
            $table->dateTime('evidence_after_hold_at')->nullable();
            $table->string('last_skip_reason', 64)->nullable();
            $table->dateTime('last_skip_at')->nullable();
            $table->timestamps();

            $table->index(['app_id', 'device_identity', 'ip', 'state'], 'risk_scopes_active_lookup');
            $table->index(['collected_at', 'ip']);
            $table->index(['ip', 'state']);
        });

        Schema::create('risk_intakes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scope_id')->index();
            $table->unsignedInteger('app_id');
            $table->string('ip', 45);
            $table->string('device_identity', 255);
            $table->string('user_id', 64)->nullable();
            $table->dateTime('collected_at');
            $table->unsignedBigInteger('source_log_id')->nullable();
            $table->timestamps();

            $table->index(['collected_at', 'ip']);
            $table->index(['app_id', 'collected_at']);
        });

        Schema::create('risk_scope_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scope_id')->index();
            $table->string('ip', 45);
            $table->string('event_type', 32);
            $table->unsignedTinyInteger('active_after')->default(1);
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('release_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'scope_id']);
            $table->index(['ip', 'occurred_at']);
        });

        Schema::create('risk_ip_exits', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->dateTime('exited_at');
            $table->unsignedBigInteger('release_id')->index();
            $table->string('status', 16)->default('effective');
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['exited_at', 'status', 'ip'], 'risk_ip_exits_range');
        });

        Schema::create('risk_releases', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->dateTime('released_at')->index();
            $table->string('status', 16)->default('released')->index();
            $table->string('rule_id', 64);
            $table->string('rule_version', 32);
            $table->unsignedInteger('resource_version')->default(1);
            $table->string('idempotency_key', 191)->unique();
            $table->json('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('ip_fully_released')->default(0);
            $table->timestamps();
        });

        Schema::create('risk_release_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->index();
            $table->unsignedBigInteger('scope_id')->index();
            $table->unsignedInteger('app_id')->index();
            $table->string('app_name')->nullable();
            $table->string('device_identity', 255)->nullable();
            $table->string('install_id', 255)->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('market_channel', 64)->nullable();
            $table->json('reason_codes')->nullable();
            $table->timestamps();

            $table->index(['device_identity']);
        });

        Schema::create('risk_revocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->index();
            $table->string('request_id', 64)->unique();
            $table->unsignedInteger('operator_id')->default(0);
            $table->string('operator_name', 64)->nullable();
            $table->dateTime('revoked_at');
            $table->string('result', 32);
            $table->json('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_ip_dossiers', function (Blueprint $table) {
            $table->string('ip', 45)->primary();
            $table->string('state', 16)->index();
            $table->string('current_decision', 16)->nullable();
            $table->unsignedInteger('active_scope_count')->default(0);
            $table->unsignedInteger('device_count')->default(0);
            $table->unsignedInteger('account_count')->default(0);
            $table->json('markets')->nullable();
            $table->json('apps')->nullable();
            $table->json('tags')->nullable();
            $table->dateTime('collected_at')->nullable();
            $table->unsignedTinyInteger('collection_known')->default(1);
            $table->dateTime('first_risk_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_log_id')->unique();
            $table->unsignedInteger('app_id')->default(0);
            $table->string('ip', 45)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->string('decision', 16)->index();
            $table->timestamps();
        });

        Schema::create('risk_day_flags', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('unclassified_logs')->default(0);
        });

        Schema::create('risk_timeline', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('event_type', 32)->index();
            $table->dateTime('occurred_at');
            $table->dateTime('received_at')->nullable();
            $table->unsignedInteger('app_id')->default(0);
            $table->string('app_name')->nullable();
            $table->string('version_name', 64)->nullable();
            $table->string('market_channel', 64)->nullable();
            $table->string('device_identity', 255)->nullable();
            $table->string('user_id', 64)->nullable();
            $table->json('reason_codes')->nullable();
            $table->string('decision', 16)->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->unsignedSmallInteger('risk_score')->nullable();
            $table->unsignedBigInteger('source_log_id')->nullable();
            $table->unsignedBigInteger('release_id')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('operator_type', 16)->nullable();
            $table->string('operator_name', 64)->nullable();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['ip', 'occurred_at']);
            $table->unique(['source_log_id', 'event_type'], 'risk_timeline_source');
        });

        Schema::create('risk_monitor_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account', 100);
            $table->string('subject_name', 100)->default('');
            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->timestamps();

            $table->unique(['account', 'subject_name']);
        });

        Schema::create('risk_activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->unsignedInteger('app_id')->index();
            $table->string('device_identity', 255);
            $table->string('install_id', 255)->nullable();
            $table->string('session_id', 64);
            $table->string('user_id', 64)->nullable();
            $table->string('version_name', 64)->nullable();
            $table->string('version_code', 64)->nullable();
            $table->string('market_channel', 64)->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
            $table->unsignedTinyInteger('foreground')->default(0);
            $table->unsignedTinyInteger('screen_interactive')->default(0);
            $table->unsignedTinyInteger('interaction')->default(0);
            $table->unsignedInteger('event_seq')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['app_id', 'device_identity', 'occurred_at'], 'risk_activity_device_time');
            $table->index(['session_id', 'event_seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_activity_events');
        Schema::dropIfExists('risk_monitor_accounts');
        Schema::dropIfExists('risk_timeline');
        Schema::dropIfExists('risk_day_flags');
        Schema::dropIfExists('risk_visits');
        Schema::dropIfExists('risk_ip_dossiers');
        Schema::dropIfExists('risk_revocations');
        Schema::dropIfExists('risk_release_scopes');
        Schema::dropIfExists('risk_releases');
        Schema::dropIfExists('risk_ip_exits');
        Schema::dropIfExists('risk_scope_events');
        Schema::dropIfExists('risk_intakes');
        Schema::dropIfExists('risk_scopes');
        Schema::dropIfExists('risk_ledger_applied');
        Schema::dropIfExists('risk_ledger_meta');
    }
};
