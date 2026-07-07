<?php

namespace App\Console\Commands;

use App\Models\MemberOrder;
use Illuminate\Console\Command;

class MemberOrderProductSnapshotFill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member-order:fill-product-snapshot {--chunk=500 : 每批处理数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '回填会员订单下单时的产品名称和产品价格快照';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunk = max(1, (int)$this->option('chunk'));
        $total = 0;

        MemberOrder::query()
            ->with('product')
            ->where(function ($query) {
                $query->where('product_name', '')
                    ->orWhere('product_price', 0);
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($orders) use (&$total) {
                foreach ($orders as $order) {
                    if (!$order->product) {
                        $this->warn("订单 {$order->id} 未找到产品 {$order->product_id}，已跳过");
                        continue;
                    }

                    $order->product_name = $order->product->name;
                    $order->product_price = $order->member_price ?: $order->product->price;
                    $order->save();
                    $total++;
                }
            });

        $this->info("会员订单产品快照回填完成，共处理 {$total} 条订单");

        return self::SUCCESS;
    }
}
