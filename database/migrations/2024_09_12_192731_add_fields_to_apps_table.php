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
        Schema::table('system_apps', function (Blueprint $table) {
            $table->string('launch_image')->default('')->after('logo');
            $table->string('contact_qq')->default('')->after('launch_image');
            $table->string('contact_email')->default('')->after('contact_qq');
            $table->string('follow_us_url')->default('')->after('contact_email');
            $table->unsignedTinyInteger('push_switch')->default(0)->after('is_del');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            $table->dropColumn('launch_image');
            $table->dropColumn('contact_qq');
            $table->dropColumn('contact_email');
            $table->dropColumn('follow_us_url');
            $table->dropColumn('push_switch');
        });
    }
};
