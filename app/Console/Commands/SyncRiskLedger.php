<?php

namespace App\Console\Commands;

use App\Services\Risk\RiskLedgerService;
use Illuminate\Console\Command;

class SyncRiskLedger extends Command
{
    protected $signature = 'app:risk-ledger-sync {--batch=500} {--max-seconds=50}';

    protected $description = '把探针日志补进风控收录账本';

    public function handle(RiskLedgerService $ledger): int
    {
        $count = $ledger->sync(max(1, (int) $this->option('batch')), max(1, (int) $this->option('max-seconds')));
        $this->info('已处理探针 ' . $count . ' 条，游标 ' . $ledger->cursor() . ($ledger->caughtUp() ? '，已追平' : '，尚未追平'));

        return self::SUCCESS;
    }
}
