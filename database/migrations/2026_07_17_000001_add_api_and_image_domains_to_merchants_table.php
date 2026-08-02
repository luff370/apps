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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('api_domain')->default('')->after('domain_expired_date')->comment('接口域名');
            $table->string('image_domain')->default('')->after('api_domain')->comment('图片域名');
            $table->string('server_subject')->default('')->after('image_domain')->comment('服务器主体');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'api_domain',
                'image_domain',
                'server_subject',
            ]);
        });
    }
};
