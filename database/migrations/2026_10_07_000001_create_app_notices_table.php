<?php

use App\Models\SystemApiInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('title', 100)->comment('公告标题');
            $table->text('content')->comment('公告内容');
            $table->unsignedInteger('sort')->default(0)->comment('排序，数字越大越靠前');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('1启用 0停用');
            $table->timestamps();

            $table->index(['app_id', 'is_enable', 'sort']);
        });

        SystemApiInterface::query()->firstOrCreate(
            ['method' => 'POST', 'path' => 'app/notices'],
            [
                'name' => '应用公告',
                'module' => 'app',
                'is_enable' => 1,
                'remark' => '获取当前应用已启用的公告，按排序返回',
                'request_params' => [],
                'response_params' => [
                    ['key' => 'list', 'type' => 'array', 'desc' => '公告列表'],
                ],
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notices');
        SystemApiInterface::query()->where('method', 'POST')->where('path', 'app/notices')->delete();
    }
};
