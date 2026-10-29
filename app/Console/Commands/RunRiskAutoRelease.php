<?php

namespace App\Console\Commands;

use App\Services\Risk\RiskReleaseEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunRiskAutoRelease extends Command
{
    protected $signature = 'app:risk-auto-release {--limit=200}';

    protected $description = '按连续使用规则评估可自动释放的风控范围';

    public function handle(RiskReleaseEngine $engine): int
    {
        $scopes = DB::table('risk_scopes')->where('state', 'active')->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        $released = 0;
        foreach ($scopes as $scope) {
            $result = $engine->tryRelease($scope);
            if ($result && empty($result['duplicate'])) {
                $released++;
            }
        }
        $this->info('检查 ' . $scopes->count() . ' 个有效范围，新释放 ' . $released . ' 条');

        return self::SUCCESS;
    }
}
