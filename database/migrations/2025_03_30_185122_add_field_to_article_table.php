<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('article', function (Blueprint $table) {
            $table->string('author')->nullable()->default('')->comment('作者')->after('sub_title');
            $table->dateTime('show_time')->nullable()->comment('展示时间')->after('views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article', function (Blueprint $table) {
            $table->dropColumn('author');
            $table->dropColumn('show_time');
        });
    }
};
