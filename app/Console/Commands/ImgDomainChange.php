<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class ImgDomainChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:img-domain-change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '图片域名更改';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = 'storeimg.appasd.com';
        $to = 'storeimg.a0g.cn';
        // traffic_violation_content 违章内容表
        $this->info('traffic_violation_content 违章内容表图片更换');
        $trafficViolationContents = TrafficViolationContent::query()
            ->where('id', '>', 500)
            ->get(['id', 'images']);

        $trafficViolationContents->each(function ($item) use ($from, $to) {
            $images = $item->images;
            foreach ($images as $key => $image) {
                $images[$key] = str_replace($from, $to, $image);
            }
            TrafficViolationContent::query()
                ->where('id', '=', $item->id)
                ->update(['images' => $images]);
        });
    }
}
