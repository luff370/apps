<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class TrafficViolationContentGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:traffic-violation-content-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成违章内容';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonString = file_get_contents(storage_path('app/tv.json'));
        $data = json_decode($jsonString, true);
        foreach ($data as $i=>$item) {
            TrafficViolationContent::query()->where('id', $i+1)->update(['city'=>trim($item['city'])]);
            // TrafficViolationContent::query()->create([
            //     'app_id' => 10002,
            //     'user_id' => 12310,
            //     'type' => $item['submitType'],
            //     'car_type' => $item['dataType'],
            //     'images' => [$item['voucher1'] ?? '', $item['voucher2'] ?? '', $item['voucher3'] ?? ''],
            //     'address' => $item['submitAddress'],
            //     'description' => $item['submitMsg'],
            //     'province_code' => explode('.', $item['dataMsg'])[0] ?? '',
            //     'license_plate_number' => explode('.', $item['dataMsg'])[1] ?? '',
            //     'violation_time' => $item['createTime'],
            //     'show_time' => $item['createTime'],
            //     'created_at' => $item['createTime'],
            //     'updated_at' => $item['createTime'],
            //     'is_exposure' => 1,
            //     'audit_status' => 1,
            //     'reward_count' => 10,
            //     'app_platform' => 'ios',
            //     'app_version' => '1.0.0',
            //     'status' => 1,
            // ]);
        }
    }
}
