<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scopes', function (Blueprint $table) {
            $table->boolean('policy_eligible')->default(true);
        });
        Schema::table('risk_ledger_meta', function (Blueprint $table) {
            $table->unsignedBigInteger('enforcement_source_id')->default(0);
        });
        // Freeze the source boundary before enabling the new policy. Existing evidence remains visible.
        $cutoff = Schema::hasTable('risk_probe_logs') ? (int) DB::table('risk_probe_logs')->max('id') : 0;
        DB::table('risk_ledger_meta')->insertOrIgnore(['id' => 1, 'cursor_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('risk_ledger_meta')->where('id', 1)->update(['enforcement_source_id' => $cutoff]);
        DB::table('risk_scopes')->update(['policy_eligible' => false]);
    }

    public function down(): void
    {
        Schema::table('risk_scopes', fn (Blueprint $table) => $table->dropColumn('policy_eligible'));
        Schema::table('risk_ledger_meta', fn (Blueprint $table) => $table->dropColumn('enforcement_source_id'));
    }
};
