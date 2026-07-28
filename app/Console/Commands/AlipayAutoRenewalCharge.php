<?php

namespace App\Console\Commands;

use App\Support\Services\AlipayAutoRenewalService;
use Illuminate\Console\Command;

class AlipayAutoRenewalCharge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alipay:auto-renewal-charge {--limit=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge due Alipay auto-renewal subscriptions';

    public function handle(AlipayAutoRenewalService $service): int
    {
        $result = $service->renewDueSubscriptions((int)$this->option('limit'));

        $this->info(sprintf(
            'Alipay auto-renewal finished. success=%d failed=%d',
            $result['success'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
