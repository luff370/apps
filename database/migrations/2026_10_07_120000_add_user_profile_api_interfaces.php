<?php

use App\Models\SystemApiInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $archive = [
            'id' => 1,
            'name' => '张三',
            'gender' => '男',
            'calendar' => '公历',
            'birth_date' => '1990-01-26 12:00',
            'birth_place' => '北京',
            'save_to_archive' => 1,
            'created_at' => '2026-10-07 12:00',
            'updated_at' => '2026-10-07 12:00',
        ];
        $request = [
            'name' => '张三',
            'gender' => '男',
            'calendar' => '公历',
            'birth_date' => '1990-01-26 12:00',
            'birth_place' => '北京',
            'save_to_archive' => 1,
        ];

        $rows = [
            [
                'name' => '档案上传',
                'path' => 'user/profile',
                'request_params' => $request,
                'response_params' => $archive,
                'remark' => '新增一条用户档案',
            ],
            [
                'name' => '档案修改',
                'path' => 'user/profile/update',
                'request_params' => ['id' => 1] + $request,
                'response_params' => $archive,
                'remark' => '修改已有用户档案',
            ],
            [
                'name' => '档案列表',
                'path' => 'user/profile/list',
                'request_params' => ['page' => 1, 'limit' => 15],
                'response_params' => [
                    'count' => 1,
                    'page' => 1,
                    'limit' => 15,
                    'list' => [$archive],
                ],
                'remark' => '分页获取当前用户的档案',
            ],
        ];

        foreach ($rows as $row) {
            SystemApiInterface::query()->updateOrCreate(
                ['method' => 'POST', 'path' => $row['path']],
                [
                    'name' => $row['name'],
                    'module' => 'user',
                    'method' => 'POST',
                    'path' => $row['path'],
                    'request_params' => $row['request_params'],
                    'response_params' => $row['response_params'],
                    'is_enable' => 1,
                    'remark' => $row['remark'],
                ]
            );
        }
    }

    public function down(): void
    {
        SystemApiInterface::query()
            ->where('method', 'POST')
            ->whereIn('path', ['user/profile/update', 'user/profile/list'])
            ->delete();
    }
};
