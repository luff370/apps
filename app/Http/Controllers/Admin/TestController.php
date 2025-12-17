<?php

namespace App\Http\Controllers\Admin;

use App\Support\Utils\SMS;
use Illuminate\Http\Request;
use App\Support\Traits\ExpressTrait;
use Yansongda\Pay\Pay;

class TestController extends Controller
{
    use ExpressTrait;

    public function index(Request $request, $action)
    {
        switch ($action) {
            case 'send_code':
                $res = SMS::sendCode('18710562367', rand(1000, 9999));

                return $this->success($res);
            case 'ip2region':
                $ip2region = new \Ip2Region();
                $result = $ip2region->simple('119.139.136.118');
                dd($result, ip2region('119.139.136.118'));
            case 'transfer':
                $result = Pay::alipay(config('pay'))->transfer([
                    'out_biz_no' => generateOrderNo(),
                    'trans_amount' => '0.01',
                    'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                    'biz_scene' => 'DIRECT_TRANSFER',
                    'payee_info' => [
                        'identity' => '18710562367',
                        'identity_type' => 'ALIPAY_LOGON_ID',
                        'name' => '潘智江'
                    ],
                ]);

                dd($result);
        }

        return $this->success();
    }
}
