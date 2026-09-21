<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_lists', function (Blueprint $table) {
            $table->id();
            $table->string('list_type', 32)->index();
            $table->string('target_type', 32)->index();
            $table->string('target_value', 255);
            $table->unsignedInteger('app_id')->default(0)->index();
            $table->string('decision', 16)->default('block');
            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->string('remark')->nullable();
            $table->string('operator', 64)->nullable();
            $table->timestamps();

            $table->index(['list_type', 'target_type', 'target_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_lists');
    }
};
