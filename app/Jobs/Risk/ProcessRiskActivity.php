<?php

namespace App\Jobs\Risk;

use App\Services\Risk\RiskActivityProjector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessRiskActivity implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [3, 10, 30, 60];
    }

    public function handle(RiskActivityProjector $projector): void
    {
        $projector->sync(100, 3);
    }
}
