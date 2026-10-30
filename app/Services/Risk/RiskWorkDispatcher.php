<?php

namespace App\Services\Risk;

use App\Jobs\Risk\ProcessRiskActivity;
use App\Jobs\Risk\ProcessRiskProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Throwable;

class RiskWorkDispatcher
{
    public function probe(int $sourceId): bool
    {
        return $this->push(new ProcessRiskProbe($sourceId));
    }

    public function activity(): bool
    {
        return $this->push(new ProcessRiskActivity);
    }

    private function push(ShouldQueue $job): bool
    {
        try {
            $connection = (string) config('risk.queue_connection', 'risk');
            $driver = config('queue.connections.'.$connection.'.driver');
            if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                throw new LogicException('Risk processing requires an asynchronous queue connection');
            }
            Queue::connection($connection)->push($job, '', (string) config('risk.queue', 'risk'));

            return true;
        } catch (Throwable $exception) {
            // The source is already durable. Scheduled inbox consumers provide recovery.
            // Never run a synchronous fallback in the business request.
            report($exception);

            return false;
        }
    }
}
