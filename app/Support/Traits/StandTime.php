<?php

namespace App\Support\Traits;

use Carbon\Carbon;

trait StandTime
{

    /**
     * 本周
     * @param $query
     * @return mixed
     */
    public function scopeWeek($query)
    {
        return $query->whereDate($this->getStatTimeField(), '>=', Carbon::now()->startOfWeek()->toDateString());
    }

    /**
     * 今日
     * @param $query
     * @return mixed
     */
    public function scopeToday($query)
    {
        return $query->whereDate($this->getStatTimeField(), Carbon::now()->toDateString());
    }

    /**
     * 昨日
     * @param $query
     * @return mixed
     */
    public function scopeYesterday($query)
    {
        return $query->whereDate($this->getStatTimeField(), Carbon::now()->subDay()->toDateString());
    }

    /**
     * 本月
     * @param $query
     * @return mixed
     */
    public function scopeMonth($query)
    {
        return $query->whereMonth($this->getStatTimeField(), Carbon::now()->format('m'));
    }

    /**
     * 获取上周数据
     * @param $query
     * @return mixed
     */
    public function scopeLast_week($query)
    {
        return $query
            ->whereDate($this->getStatTimeField(), '>=', Carbon::now()->subWeek()->startOfWeek()->toDateString())
            ->whereDate($this->getStatTimeField(), '<=', Carbon::now()->subWeek()->endOfWeek()->toDateString());
    }

    /**
     * 过去几天的数据
     * @param $query
     * @param $day
     * @return mixed
     */
    public function scopeOfLastDay($query, $day)
    {
        return $query
            ->whereDate($this->getStatTimeField(), '>', Carbon::now()->subDay($day)->toDateString());
    }

    /**
     * 时间范围
     *
     * @param $query
     * @param $range
     * @return mixed
     */
    public function scopeOfDateRange($query, $range)
    {
        list($start_date, $end_date) = explode('~', $range);
        return $query
            ->whereDate('date', '>=', $start_date)
            ->whereDate('date', '<=', $end_date);
    }

    /**
     * 获取统计时间字段
     * @return string
     */
    public function getStatTimeField()
    {
        return 'created_at';
    }
}