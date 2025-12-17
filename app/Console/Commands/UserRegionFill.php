<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserRegionFill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-region-fill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户地区信息填充';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始执行用户地区信息填充:' . now()->toDateTimeString());

        User::query()->select(['id', 'region', 'reg_ip'])->where('region', '')
            ->chunkById(100, function ($item) {
                $item->each(function ($user) {
                    $user->region = ip2region($user->reg_ip);
                    $user->save();
                });
            });

        $this->info('执行完毕:' . now()->toDateTimeString());
    }
}
