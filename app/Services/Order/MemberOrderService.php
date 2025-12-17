<?php

namespace App\Services\Order;

use App\Services\Service;
use App\Models\MemberOrder;
use App\Models\MemberProduct;
use App\Dao\Order\MemberOrderDao;
use App\Exceptions\RequestException;

class MemberOrderService extends Service
{
    public function __construct(MemberOrderDao $memberOrderDao)
    {
        $this->dao = $memberOrderDao;
    }

    /**
     * 订单创建
     *
     * @throws RequestException
     */
    public function createOrder($userId, $productId): string
    {
        $productInfo = MemberProduct::query()
            ->where('id', $productId)
            ->where('app_id', $this->getAppId())
            ->where('platform', $this->getPlatform())
            ->first();
        if (empty($productInfo)) {
            throw new RequestException('产品不存在或已下架');
        }

        $orderNo = generateOrderNo($userId);
        if (MemberOrder::query()->where('order_no', $orderNo)->exists()) {
            return $orderNo;
        }

        $order = [
            'app_id' => $this->getAppId(),
            'user_id' => $userId,
            'type' => $productInfo['is_subscribe'] == 1 ? MemberOrder::TYPE_SUBSCRIBE : MemberOrder::TYPE_SINGLE_PURCHASE, // 购买类别
            'order_no' => $orderNo,
            'member_type' => $productInfo['validity_type'],
            'quantity' => $productInfo['validity'],
            'product_id' => $productId,
            'member_price' => $productInfo['price'],
            'market_channel' => $this->getMarketChannel(),
            'version' => $this->getAppVersion(),
        ];

        if ($order['type'] == MemberOrder::TYPE_SINGLE_PURCHASE) {
            // 永久会员
            if ($order['member_type'] == 'year' && $order['quantity'] == 100) {
                $order['is_permanent'] = 1;
            }
        }

        MemberOrder::query()->create($order);

        return $orderNo;
    }

    public function tidyListData($list)
    {
        $typeMap = MemberOrder::typeMap();
        $payTypeMap = MemberOrder::payTypeMap();
        $memberTypeMap = MemberOrder::memberTypeMap();
        $memberStatusMap = MemberOrder::memberStatusMap();
        $memberStatusColorMap = MemberOrder::memberStatusColorMap();
        $payStatusMap = MemberOrder::payStatusMap();
        $payStatusColorMap = MemberOrder::payStatusColorMap();
        foreach ($list as &$item) {
            $item['type_name'] = $typeMap[$item['type']] ?? '';
            $item['member_type_name'] = $memberTypeMap[$item['member_type']] ?? '';
            $item['member_status_name'] = $memberStatusMap[$item['member_status']] ?? '';
            $item['member_status_color'] = $memberStatusColorMap[$item['member_status']] ?? '';
            $item['pay_status_name'] = $payStatusMap[$item['pay_status']] ?? '';
            $item['pay_status_color'] = $payStatusColorMap[$item['pay_status']] ?? '';
            $item['pay_type_name'] = $payTypeMap[$item['pay_type']] ?? '';
            $item['pay_time'] = empty($item['pay_time']) ? '' : date('Y-m-d H:i', $item['pay_time']);
        }

        return $list;
    }
}
