<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Yansongda\Pay\Pay;

class userTransfer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-transfer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户转账接口测试';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $result = Pay::alipay(config('pay'))->transfer([
            'out_biz_no' => generateOrderNo(),
            'trans_amount' => '0.01',
            'product_code' => 'STD_RED_PACKET',
            'biz_scene' => 'DIRECT_TRANSFER',
            'order_title' => '举报奖励',
            'remark' => '违章举报奖励红包',
            'payee_info' => [
                'identity' => '18710562367',
                'identity_type' => 'ALIPAY_LOGON_ID',
                'name' => '潘智江'
            ],
            'business_params' => "{\"sub_biz_scene\":\"REDPACKET\"}"
        ]);

        dd($result);
    }
}
