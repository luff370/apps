<?php

namespace App\Http\Controllers\Api;

use App\Models\AppPayment;
use App\Models\MemberOrder;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Support\Services\Payment;
use App\Services\Order\PaymentService;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Artful\Exception\InvalidResponseException;
use Yansongda\Pay\Provider\Wechat;
use Yansongda\Supports\Collection;

class PaymentController extends Controller
{
    /**
     * 应用支付方式列表
     */
    public function paymentList(): \Illuminate\Http\JsonResponse
    {
        $list = AppPayment::query()
            ->where("app_id", $this->getAppId())
            ->orderBy('sort', 'desc')
            ->get();

        return $this->success($list);
    }

    /**
     * 获取当前应用下已开启的支付通道列表
     *
     * 返回每个“通道 + 支付类型”组合的一条记录（避免重复）
     */
    public function availableChannels(): \Illuminate\Http\JsonResponse
    {
        $appId = $this->getAppId();

        $payments = AppPayment::query()
            ->where('app_id', $appId)
            ->where('status', true)
            ->get([
                'id',
                'pay_channel',
                'pay_type',
                'pay_app_id',
                'mch_id',
            ]);

        $channelsMap = AppPayment::payChannelMap();
        $typesMap = AppPayment::payTypeMap();

        $seen = [];
        $list = [];
        foreach ($payments as $payment) {
            $key = $payment->pay_channel . '_' . $payment->pay_type;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $list[] = [
                'pay_channel' => $payment->pay_channel,
                'pay_channel_name' => $channelsMap[$payment->pay_channel] ?? '',
                'pay_type' => $payment->pay_type,
                'pay_type_name' => $typesMap[$payment->pay_type] ?? '',
                // 支付配置所需的关键字段（前端/下单接口可按需使用）
                'pay_app_id' => $payment->pay_app_id,
            ];
        }

        return $this->success($list);
    }

    /**
     * @throws ApiException
     */
    public function orderPay(Request $request)
    {
        $orderNo = $request->get('order_no');
        $orderType = $request->get('order_type');
        $payChannel = $request->get('pay_channel');
        $payType = $request->get('pay_type');
        $supportedPayTypes = [
            Payment::PAY_TYPE_APP,
            Payment::PAY_TYPE_H5,
            Payment::PAY_TYPE_MINI,
        ];

        if (!in_array($payType, $supportedPayTypes, true)) {
            return $this->fail('未开通的支付类型：' . $payType);
        }

        switch ($orderType) {
            case "member": // 会员订单
                $order = MemberOrder::query()->where('order_no', $orderNo)->first();
                if (!$order) {
                    throw new ApiException("订单不存在,请确认订单号是否正确");
                }

                if ($order['pay_status'] == MemberOrder::PAY_STATUS_PAID) {
                    throw new ApiException('订单已支付，请勿重复支付');
                }

                if ($order['member_price'] < 0.01) {
                    throw new ApiException("订单金额不能小于0.01元");
                }

                $order->pay_type = $payChannel;
                $order->pay_source = $payType;
                $order->save();

                // $redirect_url = url('/payment/return') . "?order_id={$order_id}&type=wechat";

                switch ($payChannel) {
                    case Payment::PAY_CHANNEL_ALIPAY: // 支付宝支付
                        $payParams = [
                            'out_trade_no' => $orderNo,
                            'subject' => '会员订购',
                            'total_amount' => $order['member_price'],
                        ];
                        $payClient = Payment::getAlipayClientByType($order['app_id'], $payType, $orderNo);
                        switch ($payType) {
                            case Payment::PAY_TYPE_APP:
                                $payString = $payClient->app($payParams)->getBody()->getContents();

                                return $this->success([
                                    'pay_string' => $payString,
                                    'pay_channel' => Payment::PAY_CHANNEL_ALIPAY,
                                    'pay_type' => Payment::PAY_TYPE_APP,
                                ]);
                            case Payment::PAY_TYPE_H5:
                                $body = $payClient->h5($payParams)->getBody()->getContents();

                                return $this->success([
                                    'pay_url' => base64_encode($body),
                                ]);
                            // return view('payment.alipay', compact('body'));
                            case Payment::PAY_TYPE_MINI:
                                return $payClient->mini($payParams);
                        }

                        return $this->fail('未开通的支付宝支付类型：' . $payType);
                        break;
                    case Payment::PAY_CHANNEL_WX:
                        $payParams = [
                            'out_trade_no' => $orderNo,
                            'description' => '会员订购',
                            'amount' => [
                                'total' => (int) round((float) $order['member_price'] * 100),
                                'currency' => 'CNY',
                            ],
                        ];
                        $payClient = Payment::getWechatClientByType($order['app_id'], $payType, $orderNo);
                        switch ($payType) {
                            case Payment::PAY_TYPE_APP:
                                return $this->callWechatPay($payClient, 'app', $payParams);
                            case Payment::PAY_TYPE_H5:
                                $payDomain = rtrim(
                                    Payment::getPayUrl(Payment::PAY_CHANNEL_WX, $payType, $order['app_id'])
                                        ?: config('app.url'),
                                    '/'
                                );
                                $payParams['scene_info'] = [
                                    'payer_client_ip' => request()->ip(),
                                    'h5_info' => [
                                        'type' => 'Wap',
                                        // 'app_url' => $payDomain,
                                    ],
                                ];

                                $h5Result = $this->callWechatPay($payClient, 'h5', $payParams);
                                $redirectUrl = $payDomain . '/api/payment/return?order_no=' . $orderNo;
                                $h5Url = $h5Result->h5_url . '&redirect_url=' . rawurlencode($redirectUrl);
                                $payUrl = $h5Url;
                                if ($this->getPlatform() != 'h5') {
                                    $payUrl = $payDomain . '/pages/pay_detail/redirect.html?pay_url=' . rawurlencode(base64_encode($h5Url));
                                }

                                return $this->success([
                                    'pay_url' => $payUrl,
                                    'h5_url' => $h5Url,
                                    'pay_domain' => $payDomain,
                                ]);
                            case Payment::PAY_TYPE_MINI:
                                $payParams['payer'] = [
                                    'openid' => '123fsdf234',
                                ];

                                return $this->callWechatPay($payClient, 'mini', $payParams);
                        }

                        return $this->fail('未开通的微信支付类型：' . $payType);
                        break;

                    default:
                        return $this->fail('未开通的支付渠道：' . $payChannel);
                }

                break;
        }

        return $this->fail("错误的订单类型参数");
    }

    public function payReturn(Request $request)
    {
        $orderNo = $request->get('order_no');
        $payStatus = "unpaid";
        $productName = "会员订购";
        $amount = 0;
        $payType = '';

        $order = MemberOrder::Query()->where('order_no', $orderNo)->first();
        if (!empty($order)) {
            $payStatus = $order->pay_status;
            $amount = $order['member_price'];
            $payType = $order['pay_type'];
        }

        return view('payment.return', compact('orderNo', 'payStatus', 'productName', 'amount', 'payType'));
    }

    public function orderStatus(Request $request)
    {
        $orderNo = $request->get('order_no');
        // $orderType = $request->get('order_type');

        $order = MemberOrder::Query()->select(['pay_status'])->where('order_no', $orderNo)->first();
        if (!$order) {
            return $this->fail('订单不存在');
        }

        return $this->success(['pay_status' => $order['pay_status']]);
    }

    public function test(Request $request, PaymentService $paymentService)
    {
        $orderNo = $request->get('order_no', '202410222232098467');
        $orderType = $request->get('order_type', 'member');
        $payChannel = $request->get('pay_channel', 'alipay');
        $payType = $request->get('pay_type', 'h5');
        $body = $paymentService->orderPay($orderNo, $orderType, $payChannel, $payType);

        return view('payment.h5', compact('body'));
    }

    /**
     * @throws ApiException
     */
    private function callWechatPay(Wechat $payClient, string $method, array $payParams): mixed
    {
        try {
            return $payClient->{$method}($payParams);
        } catch (InvalidResponseException $e) {
            Log::error('微信支付下单失败', $this->buildWechatPayErrorContext($method, $payParams, $e));

            throw new ApiException($this->formatWechatPayErrorMessage($e));
        }
    }

    private function closeWechatOrder(Wechat $payClient, string $outTradeNo, string $payType): void
    {
        if ($outTradeNo === '') {
            return;
        }

        try {
            $payClient->close([
                'out_trade_no' => $outTradeNo,
                '_action' => $payType,
            ]);
        } catch (\Throwable $e) {
            Log::debug('关闭微信未支付订单（可忽略）', [
                'out_trade_no' => $outTradeNo,
                'pay_type' => $payType,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isWechatReentryParameterMismatch(InvalidResponseException $e): bool
    {
        $body = $this->extractWechatErrorBody($e);

        return ($body['code'] ?? '') === 'INVALID_REQUEST'
            && str_contains((string) ($body['message'] ?? ''), '参数与首次请求时不一致');
    }

    private function extractWechatErrorBody(InvalidResponseException $e): array
    {
        $response = $e->response;

        if ($response instanceof ResponseInterface) {
            $decoded = json_decode((string) $response->getBody(), true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($response instanceof Collection) {
            return $response->all();
        }

        return is_array($response) ? $response : [];
    }

    private function buildWechatPayErrorContext(string $method, array $payParams, InvalidResponseException $e): array
    {
        $context = [
            'method' => $method,
            'order_no' => $payParams['out_trade_no'] ?? null,
            'amount_total' => $payParams['amount']['total'] ?? null,
            'payer_client_ip' => $payParams['scene_info']['payer_client_ip'] ?? null,
            'exception' => $e->getMessage(),
        ];

        $response = $e->response;
        if ($response instanceof ResponseInterface) {
            $context['http_status'] = $response->getStatusCode();
            $context['wechat_body'] = (string) $response->getBody();
        } elseif ($response instanceof Collection) {
            $context['wechat_body'] = $response->all();
        } elseif (null !== $response) {
            $context['wechat_body'] = $response;
        }

        if (config('app.debug')) {
            $context['pay_params'] = $payParams;
        }

        return $context;
    }

    private function formatWechatPayErrorMessage(InvalidResponseException $e): string
    {
        if (!config('app.debug')) {
            return '微信支付失败，请稍后重试';
        }

        $response = $e->response;
        if ($response instanceof ResponseInterface) {
            $body = (string) $response->getBody();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $code = $decoded['code'] ?? '';
                    $detail = $decoded['message'] ?? $decoded['detail'] ?? $body;

                    return "微信支付失败 [HTTP {$response->getStatusCode()}] {$code}: {$detail}";
                }

                return "微信支付失败 [HTTP {$response->getStatusCode()}]: {$body}";
            }
        }

        return '微信支付失败: ' . $e->getMessage();
    }

}
