<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class TrafficViolationGenerateSort extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:traffic-violation-generate-sort';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '违章内容随机生成排序';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("违章内容随机生成排序定时任务,开始执行--" . now()->format('Y-m-d H:i:s'));

        $time = time();
        $ids = TrafficViolationContent::query()
            ->where('is_exposure', true)
            ->pluck('id')
            ->toArray();

        // 打乱顺序
        shuffle($ids);

        $count = count($ids);
        $dayCount = round($count / 7);
        $dayTimes = 3600 * 24;

        for ($i = 0; $i < 7; $i++) {
            $startIndex = $i * $dayCount;
            for ($j = 0; $j < $dayCount; $j++) {
                $showTime = $time - ($dayTimes * $i) - rand(1, $dayTimes);
                $id = $ids[$startIndex + $j] ?? 0;
                if ($id) {
                    TrafficViolationContent::query()->where('id', $id)->update(['show_time' => date('Y-m-d H:i:s', $showTime)]);
                }
            }
        }

        $this->info("曝光数据条数：{$count}");

        $this->info("违章内容随机生成排序定时任务,执行结束--" . now()->format('Y-m-d H:i:s'));
    }
}
