<?php

namespace App\Support\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait ModelTrait
 *
 * @package App\Support\Traits
 */
trait QueryTrait
{
    public function queryArgs(Builder $query, $args = []): Builder
    {
        foreach ($args as $field => $val) {
            if ($val !== null && $val !== '') {
                $query->where($field, $val);
            }
        }

        return $query;
    }

    public function queryOrders(Builder $query, $orders = []): Builder
    {
        foreach ($orders as $field => $sort) {
            $query->orderBy($field, $sort);
        }

        return $query;
    }

    public function queryPage(Builder $query, $limit = 0, $page = 0): Builder
    {
        if ($limit > 0) {
            if ($page > 0) {
                $query->offset(($page - 1) * $limit);
            }

            $query->limit($limit);
        }

        return $query;
    }

    /**
     * 时间段搜索器
     *
     * @param Builder $query
     * @param $timeKey
     * @param $value
     *
     * @return Builder
     */
    public function searchTime(Builder $query, $timeKey, $value): Builder
    {
        if ($value) {
            if (is_array($value)) {
                $startTime = $value[0] ?? 0;
                $endTime = $value[1] ?? 0;
                if ($startTime || $endTime) {
                    if ($startTime == $endTime || $endTime == strtotime(date('Y-m-d', $endTime))) {
                        $endTime = $endTime + 86400;
                    }
                    $query->whereBetween($timeKey, [$startTime, $endTime]);
                }
            } elseif (is_string($value)) {
                switch ($value) {
                    case 'today':
                        $query->whereBetween($timeKey, [today()->startOfDay()->unix(), today()->endOfDay()->unix()]);
                        break;
                    case 'week':
                        $startTime = Carbon::parse('this week Monday')->startOfDay()->unix();
                        $endTime = Carbon::parse('this week Sunday')->endOfDay()->unix();
                        $query->whereBetween($timeKey, [$startTime, $endTime]);
                        break;
                    case 'month':
                        $query->whereBetween($timeKey, [today()->startOfMonth()->unix(), today()->endOfMonth()->unix()]);
                        break;
                    case 'year':
                        $query->whereBetween($timeKey, [today()->startOfYear()->unix(), today()->endOfYear()->unix()]);
                        break;
                    case 'yesterday':
                        $query->whereBetween($timeKey, [today()->subDay()->startOfDay()->unix(), today()->subDay()->endOfDay()->unix()]);
                        break;
                    case 'last year':
                        $query->whereBetween($timeKey, [today()->subYear()->startOfYear()->unix(), today()->subYear()->endOfYear()->unix()]);
                        break;
                    case 'last week':
                        $startTime = Carbon::parse('this week Monday')->subWeek()->startOfDay()->unix();
                        $endTime = Carbon::parse('this week Sunday')->subWeek()->endOfDay()->unix();
                        $query->whereBetween($timeKey, [$startTime, $endTime]);
                        break;
                    case 'last month':
                        $query->whereBetween($timeKey, [today()->subMonth()->startOfMonth()->unix(), today()->subMonth()->endOfMonth()->unix()]);
                        break;
                    case 'quarter':
                        [$startTime, $endTime] = $this->getMonth();
                        $query->whereBetween($timeKey, [strtotime($startTime), strtotime($endTime)]);
                        break;
                    case 'lately7':
                        $query->whereBetween($timeKey, [strtotime("-7 day"), time()]);
                        break;
                    case 'lately30':
                        $query->whereBetween($timeKey, [strtotime("-30 day"), time()]);
                        break;
                    default:
                        if (str_contains($value, '-')) {
                            [$startTime, $endTime] = explode('-', $value);
                            $startTime = trim($startTime) ? strtotime($startTime) : 0;
                            $endTime = trim($endTime) ? strtotime($endTime) : 0;
                            if ($startTime && $endTime) {
                                if ($startTime == $endTime || $endTime == strtotime(date('Y-m-d', $endTime))) {
                                    $endTime = $endTime + 86400;
                                }
                                $query->whereBetween($timeKey, [$startTime, $endTime]);
                            } else {
                                if (!$startTime && $endTime) {
                                    $query->where($timeKey, '<', $endTime + 86400);
                                } else {
                                    if ($startTime && !$endTime) {
                                        $query->where($timeKey, '>=', $startTime);
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * 时间段搜索器
     *
     * @param Builder $query
     * @param $timeKey
     * @param $value
     *
     * @return Builder
     */
    public function searchDate(Builder $query, $timeKey, $value): Builder
    {
        if ($value) {
            if (is_array($value)) {
                $startTime = $value[0] ?? 0;
                $endTime = $value[1] ?? 0;
                if ($startTime || $endTime) {
                    $query->whereBetween($timeKey, [$startTime, $endTime]);
                }
            } elseif (is_string($value)) {
                switch ($value) {
                    case 'today':
                        $query->whereBetween($timeKey, [today()->startOfDay()->toDateTimeString(), today()->endOfDay()->toDateTimeString()]);
                        break;
                    case 'week':
                        $startTime = Carbon::parse('this week Monday')->startOfDay()->toDateTimeString();
                        $endTime = Carbon::parse('this week Sunday')->endOfDay()->toDateTimeString();
                        $query->whereBetween($timeKey, [$startTime, $endTime]);
                        break;
                    case 'month':
                        $query->whereBetween($timeKey, [today()->startOfMonth()->toDateTimeString(), today()->endOfMonth()->toDateTimeString()]);
                        break;
                    case 'year':
                        $query->whereBetween($timeKey, [today()->startOfYear()->toDateTimeString(), today()->endOfYear()->toDateTimeString()]);
                        break;
                    case 'yesterday':
                        $query->whereBetween($timeKey, [today()->subDay()->startOfDay()->toDateTimeString(), today()->subDay()->endOfDay()->toDateTimeString()]);
                        break;
                    case 'last year':
                        $query->whereBetween($timeKey, [today()->subYear()->startOfYear()->toDateTimeString(), today()->subYear()->endOfYear()->toDateTimeString()]);
                        break;
                    case 'last week':
                        $startTime = Carbon::parse('this week Monday')->subWeek()->startOfDay()->toDateTimeString();
                        $endTime = Carbon::parse('this week Sunday')->subWeek()->endOfDay()->toDateTimeString();
                        $query->whereBetween($timeKey, [$startTime, $endTime]);
                        break;
                    case 'last month':
                        $query->whereBetween($timeKey, [today()->subMonth()->startOfMonth()->toDateTimeString(), today()->subMonth()->endOfMonth()->toDateTimeString()]);
                        break;
                    case 'quarter':
                        [$startTime, $endTime] = $this->getMonth();
                        $query->whereBetween($timeKey, [$startTime, $endTime]);
                        break;
                    case 'lately7':
                        $query->whereBetween($timeKey, [today()->subDays(7)->toDateTimeString(), now()->endOfDay()->toDateTimeString()]);
                        break;
                    case 'lately30':
                        $query->whereBetween($timeKey, [today()->subDays(30)->toDateTimeString(), now()->endOfDay()->toDateTimeString()]);
                        break;
                    default:
                        if (str_contains($value, '-')) {
                            [$startTime, $endTime] = explode('-', $value);
                            $startTime = Carbon::parse(trim($startTime))->toDateTimeString();
                            $endTime = Carbon::parse(trim($endTime))->endOfDay()->toDateTimeString();
                            if ($startTime && $endTime) {
                                $query->whereBetween($timeKey, [$startTime, $endTime]);
                            } else {
                                if (!$startTime && $endTime) {
                                    $query->where($timeKey, '<', $endTime);
                                } else {
                                    if ($startTime && !$endTime) {
                                        $query->where($timeKey, '>=', $startTime);
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * 获取本季度 time
     *
     * @param int $ceil
     *
     * @return array
     */
    public function getMonth(int $ceil = 0): array
    {
        if ($ceil != 0) {
            $season = ceil(date('n') / 3) - $ceil;
        } else {
            $season = ceil(date('n') / 3);
        }
        $firstDay = date('Y-m-01', mktime(0, 0, 0, ($season - 1) * 3 + 1, 1, date('Y')));
        $lastDay = date('Y-m-t', mktime(0, 0, 0, $season * 3, 1, date('Y')));

        return [$firstDay, $lastDay];
    }
}
