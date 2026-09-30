<?php

namespace App\Console\Commands;

use App\Support\Services\RiskProbeLogPruner;
use Illuminate\Console\Command;

class PruneRiskProbeLogs extends Command
{
    protected $signature = 'app:risk-probe-logs-prune
        {--normal-days= : 无风险记录保留天数}
        {--risk-days= : 有风险或解密失败记录保留天数}
        {--batch=500 : 每批删除条数}
        {--sleep-ms=200 : 每批之间的间隔毫秒}
        {--max-seconds=3600 : 单次最长运行秒数，未删完的留给下次}';

    protected $description = '分批清理过期的设备环境探针日志';

    public function handle(RiskProbeLogPruner $pruner): int
    {
        $normalDays = (int) ($this->option('normal-days') ?: config('api_obfuscation.device_env.retention_normal_days', 30));
        $riskDays = (int) ($this->option('risk-days') ?: config('api_obfuscation.device_env.retention_risk_days', 90));
        if ($normalDays < 1 || $riskDays < $normalDays) {
            $this->error('保留天数无效：有风险记录的保留时间需要不少于无风险记录');

            return self::FAILURE;
        }

        $result = $pruner->prune(
            $normalDays,
            $riskDays,
            max(1, (int) $this->option('batch')),
            max(0, (int) $this->option('sleep-ms')),
            max(1, (int) $this->option('max-seconds')),
        );

        $message = sprintf(
            '探针清理结束：无风险 %d 条，有风险/失败 %d 条%s',
            $result['normal_deleted'],
            $result['risk_deleted'],
            $result['failed'] ? '，删除失败已中止' : ($result['finished'] ? '' : '，已达本次时限，剩余留给下次')
        );
        if ($result['failed']) {
            $this->error($message);

            return self::FAILURE;
        }
        $this->info($message);

        return self::SUCCESS;
    }
}
