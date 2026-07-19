<?php

namespace App\Services\Order;

use App\Services\Service;
use App\Models\MemberOrder;
use App\Models\MemberProduct;
use App\Dao\Order\MemberOrderDao;
use App\Models\User;
use App\Exceptions\RequestException;
use Illuminate\Support\Facades\DB;

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
            ->where('is_enable', 1)
            ->first();
        if (empty($productInfo)) {
            throw new RequestException('产品不存在或已下架');
        }

        $orderNo = generateOrderNo($userId);
        if (MemberOrder::query()->where('order_no', $orderNo)->exists()) {
            logger()->error("重复下单-订单号已存在", ['user_id' => $userId, 'product_id' => $productId]);
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
            'product_name' => $productInfo['name'],
            'product_price' => $productInfo['price'],
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
        logger()->info("会员订单创建成功", $order);

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
        $refundStatusMap = MemberOrder::refundStatusMap();
        $refundStatusColorMap = MemberOrder::refundStatusColorMap();
        foreach ($list as &$item) {
            $item['type_name'] = $typeMap[$item['type']] ?? '';
            $item['member_type_name'] = $memberTypeMap[$item['member_type']] ?? '';
            $item['member_status_name'] = $memberStatusMap[$item['member_status']] ?? '';
            $item['member_status_color'] = $memberStatusColorMap[$item['member_status']] ?? '';
            $item['pay_status_name'] = $payStatusMap[$item['pay_status']] ?? '';
            $item['pay_status_color'] = $payStatusColorMap[$item['pay_status']] ?? '';
            $item['pay_type_name'] = $payTypeMap[$item['pay_type']] ?? '';
            $item['refund_status_name'] = $refundStatusMap[(int)($item['refund_status'] ?? 0)] ?? '';
            $item['refund_status_color'] = $refundStatusColorMap[(int)($item['refund_status'] ?? 0)] ?? '';
            $item['pay_time'] = empty($item['pay_time']) ? '' : date('Y-m-d H:i', $item['pay_time']);
            $item['refund_time'] = empty($item['refund_time']) ? '' : date('Y-m-d H:i', $item['refund_time']);
            $item['product'] = [
                'id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => $item['product_price'],
            ];
        }

        return $list;
    }

    /**
     * 会员订单后台手动退款，仅记录退款结果，不调用支付渠道退款接口。
     *
     * @throws RequestException
     */
    public function refund(int $id, array $data): void
    {
        $refundPrice = $this->money($data['refund_price'] ?? 0);
        if ($refundPrice <= 0) {
            throw new RequestException('请输入退款金额');
        }

        DB::transaction(function () use ($id, $refundPrice, $data) {
            $order = MemberOrder::query()->where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new RequestException('订单不存在');
            }

            if (!$order->paid && $order->pay_status !== MemberOrder::PAY_STATUS_PAID) {
                throw new RequestException('未支付订单不能退款');
            }

            if ($order->member_status !== MemberOrder::MEMBER_STATUS_ACTIVE) {
                throw new RequestException('只有有效状态的会员订单可以退款');
            }

            if ((int)$order->refund_status === MemberOrder::REFUND_STATUS_REFUNDED) {
                throw new RequestException('订单已退款');
            }

            $payPrice = $this->money($order->pay_price);
            if ($refundPrice > $payPrice) {
                throw new RequestException('退款金额不能大于订单金额');
            }

            $order->refund_status = MemberOrder::REFUND_STATUS_REFUNDED;
            $order->refund_price = $refundPrice;
            $order->refund_time = time();
            $order->member_status = MemberOrder::MEMBER_STATUS_EXPIRED;
            if (trim((string)($data['remark'] ?? '')) !== '') {
                $order->remark = trim((string)$data['remark']);
            }
            $order->save();

            $user = User::query()->where('id', $order->user_id)->lockForUpdate()->first();
            if ($user) {
                $user->is_vip = 0;
                $user->vip_type = 0;
                $user->overdue_time = time();
                $user->expires_date = now()->toDateTimeString();
                $user->save();
            }
        });
    }

    /**
     * @throws RequestException
     */
    public function remark(int $id, string $remark): void
    {
        if ($id <= 0) {
            throw new RequestException('订单不存在');
        }

        $this->dao->update($id, ['remark' => trim($remark)]);
    }

    private function money(float|int|string $value): float
    {
        return round((float)$value, 2);
    }
}
