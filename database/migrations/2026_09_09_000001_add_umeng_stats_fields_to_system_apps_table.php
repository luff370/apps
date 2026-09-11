<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            $table->string('umeng_app_key')->default('')->after('jPush_app_secret')->comment('友盟统计App Key');
            $table->string('umeng_app_secret')->default('')->after('umeng_app_key')->comment('友盟统计Secret');
        });
    }

    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            $table->dropColumn(['umeng_app_key', 'umeng_app_secret']);
        });
    }
};
