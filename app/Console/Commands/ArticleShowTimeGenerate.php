<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class ArticleShowTimeGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:article-generate-showtime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成、更新文章展示时间';

    /**
     * 需要排序的应用ID
     *
     * @var array|int[]
     */
    protected array $appIds = [10032];

    /**
     * 分割天数
     *
     * @var int
     */
    protected int $days = 1;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("文章内容随机生成排序定时任务,开始执行--" . now()->format('Y-m-d H:i:s'));

        foreach ($this->appIds as $appId) {


            $time = time();
            $ids = Article::query()->where("app_id", $appId)->pluck("id")->toArray();

            // 打乱顺序
            shuffle($ids);

            $count = count($ids);
            $dayCount = round($count / $this->days);
            $dayTimes = 3600 * 24;

            for ($i = 0; $i < $this->days; $i++) {
                $startIndex = $i * $dayCount;
                for ($j = 0; $j < $dayCount; $j++) {
                    $showTime = $time - ($dayTimes * $i) - rand(1, $dayTimes);
                    $id = $ids[$startIndex + $j] ?? 0;
                    if ($id) {
                        Article::query()->where('id', $id)->update(['show_time' => date('Y-m-d H:i:s', $showTime)]);
                    }
                }
            }

            $this->info("文章数据条数：{$count}");
        }

        $this->info("文章内容随机生成排序定时任务,执行结束--" . now()->format('Y-m-d H:i:s'));
    }
}
