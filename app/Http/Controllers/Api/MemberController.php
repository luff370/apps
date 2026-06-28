<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\MemberProduct;
use App\Models\UserWithdrawal;
use App\Exceptions\RequestException;
use App\Services\Order\MemberOrderService;

class MemberController extends Controller
{
    public function info(Request $request): \Illuminate\Http\JsonResponse
    {
        $info = [
            'image' => '',
            'equity' => [
                'Chat-4大语言模型',
                '200+海量场景，持续更新！',
                '每日赠送50000字数！',
                '无广告！无广告！无广告！',
            ],
            'buy_info' => '免费试用3天，此后仅需168/年',
            'member_agreement' => '',
        ];

        return $this->success($info);
    }

    public function list(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = MemberProduct::query()
            ->where('app_id', $this->getAppId())
            ->whereIn('platform', ['all', strtolower($this->getPlatform())])
            ->where('is_enable', 1)
            ->orderBy('sort', 'desc');
        if ($this->getAppId() == 10008 && $this->getLanguage()) {
            $query->where('lang', $this->getLanguage());
        }
        $list = $query->get();

        if ($this->getAppId() == 10038) {
            $productIds = $list->pluck('id')->toArray();
            $productIdsForWithdraw = UserWithdrawal::query()
                ->whereIn('product_id', $productIds)
                ->where('today_withdrawal_count_mark', 1)
                ->where('apply_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
                ->pluck('product_id')
                ->toArray();

            $list = $list->map(function ($item) use ($productIdsForWithdraw) {
                $item->total_withdrawal_count = 1;
                if (in_array($item->id, $productIdsForWithdraw)) {
                    $item->withdrawal_count = 1;
                }
                return $item;
            });
        }

        return $this->success($list);
    }

    /**
     * @throws RequestException
     */
    public function order(Request $request, MemberOrderService $orderService): \Illuminate\Http\JsonResponse
    {
        $productId = $request->get('product_id');

        $orderNo = $orderService->createOrder(authUserId(), $productId);

        return $this->success(['order_no' => $orderNo]);
    }
}
