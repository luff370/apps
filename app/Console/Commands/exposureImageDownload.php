<?php

namespace App\Console\Commands;

use App\Models\TrafficViolationContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class exposureImageDownload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:exposure-image-download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '曝光数据图片打包下载';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 定义存储目录
        $localDir = 'images/';

        // 确保本地目录存在
        if (!Storage::exists($localDir)) {
            Storage::makeDirectory($localDir);
        }

        $images = TrafficViolationContent::query()->where('is_exposure', 1)->pluck('images');
        // 下载图片到本地存储
        foreach ($images as $key => $imageArr) {
            foreach ($imageArr as $image) {
                $imageContent = file_get_contents($image);
                $imageName = basename($image); // 获取图片的文件名
                Storage::put($localDir . ($key + 1) . "/" . $imageName, $imageContent); // 存储图片到本地
            }
        }
    }


}
