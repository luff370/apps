<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_attachment', function (Blueprint $table) {
            $table->string('plan_remark_draft', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('system_attachment', function (Blueprint $table) {
            $table->dropIndex(['plan_remark_draft']);
        });
        Schema::table('system_attachment', function (Blueprint $table) {
            $table->dropColumn('plan_remark_draft');
        });
    }
};
