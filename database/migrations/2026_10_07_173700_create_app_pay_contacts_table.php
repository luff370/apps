<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_pay_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->comment('联系人名称');
            $table->string('image', 500)->comment('微信二维码图片');
            $table->unsignedInteger('sort')->default(0)->comment('排序，数字越大越靠前');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('1启用 0停用');
            $table->timestamps();

            $table->index(['is_enable', 'sort']);
        });

        Schema::create('app_pay_contact_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->comment('联系人ID');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('assign_count')->default(0)->comment('该应用下已分配次数');
            $table->timestamps();

            $table->unique(['contact_id', 'app_id']);
            $table->index(['app_id', 'assign_count']);
            $table->foreign('contact_id')->references('id')->on('app_pay_contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_pay_contact_apps');
        Schema::dropIfExists('app_pay_contacts');
    }
};
