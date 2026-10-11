<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agents') && !Schema::hasTable('ai_agents')) {
            Schema::rename('agents', 'ai_agents');
        }

        $this->renameIndex('ai_agents', 'agents_app_key_unique', 'ai_agents_app_key_unique');
        $this->renameIndex('ai_agents', 'tianji_agents_app_key_unique', 'ai_agents_app_key_unique');
    }

    public function down(): void
    {
        $this->renameIndex('ai_agents', 'ai_agents_app_key_unique', 'agents_app_key_unique');

        if (Schema::hasTable('ai_agents') && !Schema::hasTable('agents')) {
            Schema::rename('ai_agents', 'agents');
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
