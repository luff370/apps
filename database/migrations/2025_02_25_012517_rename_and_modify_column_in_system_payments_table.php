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
        Schema::table('system_payments', function (Blueprint $table) {
            $table->renameColumn('cert_patch', 'mch_public_cert');
            $table->renameColumn('notify_url', 'mch_root_cert');
        });

        Schema::table('system_payments', function (Blueprint $table) {
            $table->string('mch_root_cert')->comment('商户根证书')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_payments', function (Blueprint $table) {
            $table->renameColumn('mch_public_cert', 'cert_patch');
            $table->renameColumn('mch_root_cert', 'notify_url');
        });
    }
};
