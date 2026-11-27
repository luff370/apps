<?php

namespace App\Console\Commands;

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\ProjectionRiskRepair;
use App\Services\Risk\RiskLedgerService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class RepairProjectionRisk extends Command
{
    protected $signature = 'app:risk-repair-projection
        {--paused-signals : 核对暂停计分标签的历史处置}
        {--apply-paused : 直接分批修正全部暂停计分标签的历史处置}
        {--apply : 执行修正，默认仅预览}
        {--all : 自动分批处理至固定的历史范围末尾}
        {--ip= : 只检查指定 IP}
        {--after-id=0}
        {--until-id=0}
        {--limit=200 : 每批最多检查的处置数}
        {--max-seconds=10 : 每批检查的时间预算}';

    protected $description = '核验已停用计分信号造成的历史处置，解除或重算分数并保留原始日志';

    public function handle(ProjectionRiskRepair $repair, RiskLedgerService $ledger): int
    {
        $applyPaused = (bool) $this->option('apply-paused');
        $apply = $applyPaused || (bool) $this->option('apply');
        $paused = $applyPaused || (bool) $this->option('paused-signals');
        $all = $applyPaused || (bool) $this->option('all');
        $after = max(0, (int) $this->option('after-id'));
        $until = max(0, (int) $this->option('until-id'));
        $limit = (int) $this->option('limit');
        $seconds = max(1, min(60, (int) $this->option('max-seconds')));
        $ipOption = $this->option('ip');
        $ip = $this->input->hasParameterOption('--ip') ? IpNormalizer::normalize($ipOption) : null;
        if ($ip === '') {
            $this->error('请提供完整有效的 IP 地址。');

            return self::INVALID;
        }
        $report = $repair->run($apply, $after, $until, $limit, $seconds, $paused, $ip);
        if (! $all) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $report['batches'] = 1;
        while ($report['has_more'] && empty($report['skipped']['inbox_pending'])) {
            $this->line(sprintf('已完成 %d 批，检查 %d 条处置', $report['batches'], $report['scanned']), null, OutputInterface::VERBOSITY_VERBOSE);
            usleep(250_000);
            $batch = $repair->run($apply, $report['last_scope_id'], $report['until_id'], $limit, $seconds, $paused, $ip);
            foreach (['scanned', 'eligible', 'released', 'rescore_eligible', 'rescored'] as $key) {
                $report[$key] += $batch[$key];
            }
            foreach ($batch['skipped'] as $reason => $count) {
                $report['skipped'][$reason] = ($report['skipped'][$reason] ?? 0) + $count;
            }
            $report['eligible_sample'] = array_slice(array_merge($report['eligible_sample'], $batch['eligible_sample']), 0, 20);
            if ($ip !== null) {
                $report['skipped_scope_sample'] = array_slice(array_merge($report['skipped_scope_sample'], $batch['skipped_scope_sample']), 0, 20);
            }
            $report['last_scope_id'] = $batch['last_scope_id'];
            $report['has_more'] = $batch['has_more'];
            $report['batches']++;
        }

        $pending = ! empty($report['skipped']['inbox_pending']);
        $report['complete'] = ! $pending && ! $report['has_more'];
        if ($pending) {
            $report['ledger_progress'] = $ledger->progress();
        }
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($pending) {
            $this->output->getErrorStyle()->warning('风控账本尚未追平。待队列处理完毕后，重新执行同一条命令即可。');
        }

        return $report['complete'] ? self::SUCCESS : self::FAILURE;
    }
}
