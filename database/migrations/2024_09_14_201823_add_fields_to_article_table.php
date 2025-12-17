<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('article', function (Blueprint $table) {
            //
            $table->string('source', 32)->default('')->after('code')->comment('三方资源来源');
            $table->string('duration', 32)->default('')->after('code')->comment('多媒体资源时长');
            $table->unsignedTinyInteger('collections')->default(0)->after('code')->comment('合集资源数量');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article', function (Blueprint $table) {
            //
            $table->dropColumn('source');
            $table->dropColumn('duration');
            $table->dropColumn('collections');
        });
    }
};
