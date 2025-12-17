<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MemberStatusAutoUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:member-status-auto-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '会员状态自动更新';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 自动更新已过期会员状态
        User::query()->where('is_vip', 1)
            ->where('vip_type',1)
            ->where('overdue_time','<', time())
            ->update(['is_vip' => 0]);
    }
}
