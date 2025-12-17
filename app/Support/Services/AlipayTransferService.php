<?php

namespace App\Support\Services;
use Alipay\EasySDK\Kernel\Factory;

class AlipayTransferService
{
    public function transfer($userId, $amount, $orderNo)
    {
        $result = Factory::payment()->common()->execute('alipay.fund.trans.uni.transfer', [
            'biz_content' => json_encode([
                'out_biz_no' => $orderNo,
                'trans_amount' => number_format($amount, 2, '.', ''),
                'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                'biz_scene' => 'DIRECT_TRANSFER',
                'order_title' => '用户提现',
                'payee_info' => [
                    'identity' => $userId, // 来自授权
                    'identity_type' => 'ALIPAY_USER_ID',
                ],
            ]),
        ]);

        if ($result->code === '10000') {
            return ['success' => true, 'trade_no' => $result->order_id];
        }

        return ['success' => false, 'msg' => $result->sub_msg ?? '转账失败'];
    }
}

