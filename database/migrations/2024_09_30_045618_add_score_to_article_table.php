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
        Schema::table('article', function (Blueprint $table) {
            //
            $table->unsignedTinyInteger('score')->default(5)->comment('评分')->after('type');
            $table->unsignedInteger('views')->default(0)->comment('访问数')->after('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article', function (Blueprint $table) {
            //
            $table->dropColumn('score');
            $table->dropColumn('views');
        });
    }
};
