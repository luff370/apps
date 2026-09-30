<?php

namespace App\Support\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 分批删除过期探针。按主键删，避免范围删除锁住正在写入的新行。
 */
class RiskProbeLogPruner
{
    private bool $stoppedEarly = false;

    private bool $failed = false;

    public function prune(int $normalDays, int $riskDays, int $batch = 500, int $sleepMs = 200, int $maxSeconds = 3600): array
    {
        $this->stoppedEarly = false;
        $this->failed = false;
        $deadline = microtime(true) + max(1, $maxSeconds);
        logger()->info('探针日志清理开始', [
            'normal_days' => $normalDays,
            'risk_days' => $riskDays,
            'batch' => $batch,
        ]);

        $riskDeleted = $this->deleteOlderThan('有风险/解密失败', now()->subDays($riskDays)->toDateTimeString(), false, $batch, $sleepMs, $deadline);
        $normalDeleted = $this->deleteOlderThan('无风险', now()->subDays($normalDays)->toDateTimeString(), true, $batch, $sleepMs, $deadline);

        $result = [
            'risk_deleted' => $riskDeleted,
            'normal_deleted' => $normalDeleted,
            'finished' => !$this->stoppedEarly && !$this->failed,
            'failed' => $this->failed,
        ];
        if ($this->failed) {
            logger()->error('探针日志清理失败', $result);
        } else {
            logger()->info('探针日志清理完成', $result);
        }

        return $result;
    }

    private function deleteOlderThan(string $label, string $before, bool $quietOnly, int $batch, int $sleepMs, float $deadline): int
    {
        if ($this->stoppedEarly || $this->failed) {
            return 0;
        }

        $deleted = 0;
        foreach (['ok', 'error'] as $status) {
            if ($quietOnly && $status !== 'ok') {
                continue;
            }
            while (true) {
                if (microtime(true) >= $deadline) {
                    $this->stoppedEarly = true;
                    logger()->info('探针日志清理达到本次时限', [
                        'label' => $label,
                        'status' => $status,
                        'deleted' => $deleted,
                    ]);
                    break 2;
                }
                try {
                    $ids = $this->nextIds($status, $before, $quietOnly, $batch);
                } catch (\Throwable $e) {
                    $this->failed = true;
                    logger()->error('探针日志清理查询失败', [
                        'label' => $label,
                        'status' => $status,
                        'before' => $before,
                        'error' => $e->getMessage(),
                    ]);
                    break 2;
                }
                if ($ids === []) {
                    break;
                }
                try {
                    $count = DB::table('risk_probe_logs')->whereIn('id', $ids)->delete();
                } catch (\Throwable $e) {
                    $this->failed = true;
                    logger()->error('探针日志清理删除失败', [
                        'label' => $label,
                        'status' => $status,
                        'before' => $before,
                        'id_from' => $ids[0],
                        'id_to' => $ids[count($ids) - 1],
                        'batch' => count($ids),
                        'error' => $e->getMessage(),
                    ]);
                    break 2;
                }
                $deleted += $count;
                logger()->info('探针日志清理删除成功', [
                    'label' => $label,
                    'status' => $status,
                    'deleted' => $count,
                    'total' => $deleted,
                ]);
                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        return $deleted;
    }

    private function nextIds(string $status, string $before, bool $quietOnly, int $batch): array
    {
        $query = DB::table('risk_probe_logs')
            ->where('status', $status)
            ->where('created_at', '<', $before);
        if ($quietOnly) {
            $this->quiet($query);
        }

        return $query->orderBy('created_at')->limit($batch)->pluck('id')->all();
    }

    /**
     * 0 分、没有风险原因、广告未关、也没有校验异常。这类记录只需要短期留存。
     */
    private function quiet(Builder $query): void
    {
        $query->where('risk_score', 0)
            ->where('compliance_mode', 0)
            ->where('ad_switch', 1)
            ->where(function (Builder $inner) {
                $inner->whereNull('env_allows_ads')->orWhere('env_allows_ads', 1);
            })
            ->where(function (Builder $inner) {
                $inner->whereNull('risk_reasons')->orWhereRaw('JSON_LENGTH(risk_reasons) = 0');
            })
            ->where(function (Builder $inner) {
                $inner->whereNull('validation_errors')->orWhereRaw('JSON_LENGTH(validation_errors) = 0');
            });
    }
}
