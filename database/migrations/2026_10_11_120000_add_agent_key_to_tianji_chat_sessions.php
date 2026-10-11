<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tianji_chat_sessions', 'agent_key')) {
            Schema::table('tianji_chat_sessions', function (Blueprint $table) {
                $table->string('agent_key', 64)->default('')->after('target_key')->comment('智能体标识');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tianji_chat_sessions', 'agent_key')) {
            Schema::table('tianji_chat_sessions', function (Blueprint $table) {
                $table->dropColumn('agent_key');
            });
        }
    }
};
