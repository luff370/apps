<?php

use App\Models\SystemApiInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $interface = SystemApiInterface::query()
            ->where('method', 'POST')
            ->where('path', 'app/umeng/initialize')
            ->first();

        $interface?->update([
            'request_params' => ['oaid' => 'example-oaid'],
            'response_params' => ['status' => 200, 'msg' => 'success', 'data' => null],
        ]);
    }

    public function down(): void
    {
        // 原始 JSON 样例与现有别名兼容。
    }
};
