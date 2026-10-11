<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTable('tianji_agents', 'agents');
        $this->renameTable('tianji_chat_sessions', 'agent_chat_sessions');
        $this->renameTable('tianji_chat_messages', 'agent_chat_messages');

        $this->renameIndex('agents', 'tianji_agents_app_key_unique', 'agents_app_key_unique');
        $this->renameIndex('agent_chat_sessions', 'tianji_chat_sessions_agent_index', 'agent_chat_sessions_agent_index');
        $this->renameIndex('agent_chat_messages', 'tianji_chat_messages_session_id_id_index', 'agent_chat_messages_session_id_id_index');
    }

    public function down(): void
    {
        $this->renameIndex('agents', 'agents_app_key_unique', 'tianji_agents_app_key_unique');
        $this->renameIndex('agent_chat_sessions', 'agent_chat_sessions_agent_index', 'tianji_chat_sessions_agent_index');
        $this->renameIndex('agent_chat_messages', 'agent_chat_messages_session_id_id_index', 'tianji_chat_messages_session_id_id_index');

        $this->renameTable('agent_chat_messages', 'tianji_chat_messages');
        $this->renameTable('agent_chat_sessions', 'tianji_chat_sessions');
        $this->renameTable('agents', 'tianji_agents');
    }

    private function renameTable(string $from, string $to): void
    {
        if (Schema::hasTable($from) && !Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameIndex(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $names = Schema::getIndexListing($table);
        if (!in_array($from, $names, true) || in_array($to, $names, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to) {
            $table->renameIndex($from, $to);
        });
    }
};
