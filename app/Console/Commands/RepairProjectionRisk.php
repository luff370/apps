<?php

namespace App\Console\Commands;

use App\Services\Risk\ProjectionRiskRepair;
use Illuminate\Console\Command;

class RepairProjectionRisk extends Command
{
    protected $signature = 'app:risk-repair-projection {--apply : 执行修正，默认仅预览} {--after-id=0} {--until-id=0} {--limit=200} {--max-seconds=10}';

    protected $description = '核验并解除仅投屏导致的历史处置，保留原始日志和修正审计';

    public function handle(ProjectionRiskRepair $repair): int
    {
        $report = $repair->run((bool) $this->option('apply'), max(0, (int) $this->option('after-id')), max(0, (int) $this->option('until-id')),
            (int) $this->option('limit'), max(1, min(60, (int) $this->option('max-seconds'))));
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
