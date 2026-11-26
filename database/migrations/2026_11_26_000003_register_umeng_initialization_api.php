<?php

use App\Models\SystemApiInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $interface = SystemApiInterface::query()->firstOrNew([
            'method' => 'POST',
            'path' => 'app/umeng/initialize',
        ]);

        if ($interface->exists) {
            return;
        }

        $interface->fill([
            'name' => '友盟初始化上报',
            'module' => 'app',
            'is_enable' => $interface->exists ? $interface->is_enable : 1,
            'remark' => '友盟初始化成功并取得 OAID 后调用。设备身份沿用 app/info 的请求头和 Device-Env。',
            'request_params' => ['oaid' => 'example-oaid'],
            'response_params' => ['status' => 200, 'msg' => 'success', 'data' => null],
        ])->save();
    }

    public function down(): void
    {
        // 已登记的接口可能被应用别名引用，回滚时保留目录记录。
    }
};
