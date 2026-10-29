<?php

namespace App\Services\Risk\Ledger;

class UsageWindow
{
    /**
     * @param  array<int, array{at:int,session_id:string,foreground:bool,screen_interactive:bool,interaction:bool}>  $events
     * @return array{qualified:bool,effective_seconds:int,threshold_reached_at:?int,qualifying_interaction_at:?int,session_id:string}
     */
    public static function measure(
        array $events,
        int $thresholdSeconds = ReleaseRule::THRESHOLD_SECONDS,
        int $gapSeconds = ReleaseRule::GAP_SECONDS,
        int $idleSeconds = ReleaseRule::IDLE_STOP_SECONDS,
        int $backgroundSplitSeconds = ReleaseRule::BACKGROUND_SPLIT_SECONDS,
    ): array {
        usort($events, static function (array $a, array $b) {
            return $a['at'] <=> $b['at'] ?: (($a['seq'] ?? 0) <=> ($b['seq'] ?? 0));
        });

        $empty = [
            'qualified' => false,
            'effective_seconds' => 0,
            'threshold_reached_at' => null,
            'qualifying_interaction_at' => null,
            'session_id' => '',
        ];
        $accum = 0;
        $thresholdAt = null;
        $lastInteraction = null;
        $prev = null;
        $frozen = null;

        foreach ($events as $event) {
            $split = $prev && (
                (string) $event['session_id'] !== (string) $prev['session_id']
                || ($event['at'] - $prev['at']) > $backgroundSplitSeconds
            );
            if ($split) {
                $accum = 0;
                $thresholdAt = null;
                $lastInteraction = null;
                $prev = null;
            }
            if ($prev) {
                $gap = $event['at'] - $prev['at'];
                $countable = $prev['foreground'] && $prev['screen_interactive'] && $lastInteraction !== null;
                if ($gap > 0 && $gap <= $gapSeconds && $countable) {
                    $budget = $idleSeconds - ($prev['at'] - $lastInteraction);
                    if ($budget > 0) {
                        $add = min($gap, $budget);
                        $before = $accum;
                        $accum += $add;
                        if ($thresholdAt === null && $accum >= $thresholdSeconds) {
                            $thresholdAt = $prev['at'] + ($thresholdSeconds - $before);
                        }
                    }
                }
            }
            // 后台或锁屏上报不能刷新有效交互时间，也不能触发达标后的释放。
            if (!empty($event['interaction']) && $event['foreground'] && $event['screen_interactive']) {
                $lastInteraction = $event['at'];
                if ($frozen === null && $thresholdAt !== null && $event['at'] > $thresholdAt) {
                    $frozen = [
                        'qualified' => true,
                        'effective_seconds' => $accum,
                        'threshold_reached_at' => $thresholdAt,
                        'qualifying_interaction_at' => $event['at'],
                        'session_id' => (string) $event['session_id'],
                    ];
                }
            }
            $prev = $event;
        }

        if ($frozen) {
            return $frozen;
        }
        $empty['effective_seconds'] = $accum;

        return $empty;
    }
}
