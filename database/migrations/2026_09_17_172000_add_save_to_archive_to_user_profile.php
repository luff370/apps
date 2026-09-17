<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->unsignedTinyInteger('save_to_archive')->default(0)->comment('是否保存档案')->after('birth_place');
        });
    }

    public function down(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropColumn('save_to_archive');
        });
    }
};
