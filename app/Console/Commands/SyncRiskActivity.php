<?php

namespace App\Console\Commands;

use App\Services\Risk\RiskActivityProjector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncRiskActivity extends Command
{
    protected $signature = 'app:risk-activity-sync {--batch=100} {--max-seconds=10}';

    protected $description = '后台处理活动事实、账号关联、档案和自动释放';

    public function handle(RiskActivityProjector $projector): int
    {
        $seconds = max(1, min(30, (int) $this->option('max-seconds')));
        $lock = Cache::lock('risk:activity-projector', $seconds + 60);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $count = $projector->sync((int) $this->option('batch'), $seconds);
            $this->info('已处理活动 '.$count.' 条');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
