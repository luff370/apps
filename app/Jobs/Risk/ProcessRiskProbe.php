<?php

namespace App\Jobs\Risk;

use App\Models\RiskProbeLog;
use App\Services\Risk\RiskLedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessRiskProbe implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $sourceId)
    {
    }

    public function backoff(): array
    {
        return [3, 10, 30, 60];
    }

    public function handle(RiskLedgerService $ledger): void
    {
        // Ordered projection preserves chronology even when jobs arrive out of order.
        $ledger->sync(200, 2);
        if ($ledger->cursor() >= $this->sourceId) {
            // Repair a source committed late below the checkpoint; normal delivery is an idempotent no-op.
            $log = RiskProbeLog::query()->find($this->sourceId);
            if ($log) {
                $ledger->applyLog($log);
            }
        }
    }
}
