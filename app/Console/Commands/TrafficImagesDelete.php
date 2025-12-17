<?php

namespace App\Console\Commands;

use App\Models\TrafficViolationContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class TrafficImagesDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:traffic-images-delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '删除违章举报过期数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("删除违章举报过期数据 定时任务执行开始：" . now()->format('Y-m-d H:i:s'));

        // 过期天数
        $days = 15;

        // 删除30天前所有未设置曝光的数据
        $data = TrafficViolationContent::query()
            ->whereBetween("created_at", [today()->subDays($days + 1), today()->subDays($days)])
            ->where("is_exposure", 0)
            ->where("app_audit_data", 0)
            ->get(['id', 'images']);

        foreach ($data as $item) {
            foreach ($item->images as $image) {
                $res = $this->deleteImageByUrl($image);
                $this->info($res);
            }
        }

        $this->info("删除违章举报过期数据 定时任务执行结束：" . now()->format('Y-m-d H:i:s'));
    }

    public function deleteImageByUrl($imageUrl): string
    {
        // 解析URL，获取文件路径
        $filePath = parse_url($imageUrl, PHP_URL_PATH);

        // 确保路径没有前导斜杠，例如 /storage/image.jpg
        $filePath = ltrim($filePath, '/');

        // 使用 Storage 删除文件
        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
            return "{$imageUrl} deleted successfully.";
        }

        // 如果你使用 File 类来删除文件
        if (File::exists(public_path($filePath))) {
            File::delete(public_path($filePath));
            return "{$imageUrl} deleted successfully.";
        }

        return "{$imageUrl} not found.";
    }

}
