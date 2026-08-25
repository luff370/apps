<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_domain', function (Blueprint $table) {
            $table->date('expire_at')->nullable()->comment('到期日期')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_domain', function (Blueprint $table) {
            $table->dateTime('expire_at')->nullable()->comment('到期时间')->change();
        });
    }
};
