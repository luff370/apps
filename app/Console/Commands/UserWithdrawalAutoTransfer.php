<?php

namespace App\Console\Commands;

use App\Models\UserWithdrawal;
use Illuminate\Console\Command;
use App\Support\Traits\ServicesTrait;

class UserWithdrawalAutoTransfer extends Command
{
    use ServicesTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-withdrawal-auto-transfer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '违章用户提现自动转账';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        logger()->info("违章用户提现自动转账,定时任务开始执行--" . now()->toDateTimeString());

        $afterDays = 3;
        $appIds = [10002, 10016];
        $amount = 0.01;
        $data = UserWithdrawal::query()
            // ->whereBetween('created_at', [today()->subDays($afterDays), today()->subDays($afterDays - 1)])
            ->whereIn('app_id', $appIds)
            ->where('audit_status', 0)
            ->where('transfer_status', 0)
            ->where('amount', $amount)
            ->limit(200)
            ->get();

        if (empty($data)) {
            logger()->info("没有待处理的转账数据");

            return false;
        }

        foreach ($data as $item) {
            $this->userWithdrawalService()->autoTransfer($item);
        }

        logger()->info("违章用户提现自动转账,定时任务执行结束--" . now()->toDateTimeString());

        return true;
    }
}
