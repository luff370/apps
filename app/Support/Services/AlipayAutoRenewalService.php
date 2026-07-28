<?php

namespace App\Support\Services;

use App\Exceptions\ApiException;
use App\Models\MemberOrder;
use App\Models\MemberProduct;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Order\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Plugin\Alipay\V2\AddPayloadSignaturePlugin;
use Yansongda\Pay\Plugin\Alipay\V2\AddRadarPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\FormatPayloadBizContentPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\Pay\Agreement\Sign\SignPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\ResponseHtmlPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\StartPlugin;
use Yansongda\Artful\Plugin\ParserPlugin;

class AlipayAutoRenewalService
{
    private const DEFAULT_SIGN_SCENE = 'INDUSTRY|APP_STORE';
    private const DEFAULT_SIGN_PRODUCT_CODE = 'CYCLE_PAY_AUTH_P';
    private const DEFAULT_PAY_PRODUCT_CODE = 'GENERAL_WITHHOLDING';

    /**
     * @throws ApiException
     */
    public function createSignPayload(MemberOrder $order, string $payType): array
    {
        if (!in_array($payType, [Payment::PAY_TYPE_APP, Payment::PAY_TYPE_H5], true)) {
            throw new ApiException('支付宝自动续费签约仅支持 APP 或 H5');
        }

        if ((int)$order->type !== MemberOrder::TYPE_SUBSCRIBE) {
            throw new ApiException('当前订单不是自动续费订单');
        }

        if ($order->pay_status === MemberOrder::PAY_STATUS_PAID) {
            throw new ApiException('订单已支付，请勿重复签约');
        }

        $product = MemberProduct::query()->where('id', $order->product_id)->first();
        if (!$product || (int)$product->is_subscribe !== 1) {
            throw new ApiException('订阅商品不存在或未开启自动续费');
        }

        $subscription = $this->ensurePendingSubscription($order, $product);
        $payConfig = Payment::getAlipayConfigByType($payType, $order->app_id, $order->order_no);
        $payConfig['return_url'] = url('/api/payment/alipay/agreement/return')
            . '?payment_id=' . $payConfig['id']
            . '&order_no=' . rawurlencode($order->order_no);
        $payConfig['notify_url'] = url('/api/payment/alipay/agreement/' . $payConfig['id'] . '/notify');

        $payClient = Pay::alipay(Payment::tidyForPayConfig('alipay', $payConfig));
        $response = $payClient->pay($this->signPagePlugins(), $this->buildSignParams($order, $product, $subscription, $payConfig));
        $body = $response instanceof ResponseInterface ? $response->getBody()->getContents() : (string)$response;
        $signUrl = $response instanceof ResponseInterface ? $response->getHeaderLine('Location') : '';

        $result = [
            'order_no' => $order->order_no,
            'external_agreement_no' => $subscription->external_agreement_no,
            'pay_channel' => Payment::PAY_CHANNEL_ALIPAY,
            'pay_type' => $payType,
            'sign_url' => $signUrl ?: $this->extractAlipayQueryFromHtml($body),
        ];

        if ($payType === Payment::PAY_TYPE_APP) {
            $result['pay_string'] = $result['sign_url'];
            return $result;
        }

        $result['sign_html'] = base64_encode($body);

        return $result;
    }

    /**
     * @throws ApiException
     */
    public function handleAgreementCallback(array $raw, array $paymentConfig): void
    {
        $alipay = Pay::alipay(Payment::tidyForPayConfig('alipay', $paymentConfig));
        $data = $alipay->callback($raw);

        $externalAgreementNo = (string)($data['external_agreement_no'] ?? '');
        $agreementNo = (string)($data['agreement_no'] ?? '');
        $status = (string)($data['status'] ?? 'NORMAL');

        if ($externalAgreementNo === '' || $agreementNo === '') {
            throw new ApiException('支付宝签约回调缺少协议号');
        }

        $subscription = SubscriptionOrder::query()
            ->where('external_agreement_no', $externalAgreementNo)
            ->first();

        if (!$subscription) {
            throw new ApiException('订阅记录不存在');
        }

        $subscription->agreement_no = $agreementNo;
        $subscription->original_transaction_id = $agreementNo;
        $subscription->agreement_status = $status;
        $subscription->auto_renew_status = 1;
        $subscription->status = 'active';
        $subscription->save();

        $order = MemberOrder::query()
            ->where('order_no', $subscription->remark)
            ->where('pay_status', '!=', MemberOrder::PAY_STATUS_PAID)
            ->first();

        if ($order) {
            $this->payByAgreement($order, $subscription, $paymentConfig, true);
        }
    }

    /**
     * @throws ApiException
     */
    public function cancel(int $userId, string $agreementNo, string $payType = Payment::PAY_TYPE_APP): void
    {
        $subscription = SubscriptionOrder::query()
            ->where('user_id', $userId)
            ->where('pay_type', Payment::PAY_CHANNEL_ALIPAY)
            ->where(function ($query) use ($agreementNo) {
                $query->where('agreement_no', $agreementNo)
                    ->orWhere('external_agreement_no', $agreementNo);
            })
            ->first();

        if (!$subscription) {
            throw new ApiException('订阅记录不存在');
        }

        $paymentConfig = Payment::getAlipayConfigByType($payType, $subscription->app_id);
        $payClient = Pay::alipay(Payment::tidyForPayConfig('alipay', $paymentConfig));
        $payClient->pay($payClient->mergeCommonPlugins([
            \Yansongda\Pay\Plugin\Alipay\V2\Pay\Agreement\Sign\UnsignPlugin::class,
        ]), [
            'agreement_no' => $subscription->agreement_no,
            'external_agreement_no' => $subscription->external_agreement_no,
            'personal_product_code' => config('pay.alipay_agreement.sign_product_code', self::DEFAULT_SIGN_PRODUCT_CODE),
            'sign_scene' => config('pay.alipay_agreement.sign_scene', self::DEFAULT_SIGN_SCENE),
        ]);

        $subscription->status = 'canceled';
        $subscription->agreement_status = 'UNSIGN';
        $subscription->auto_renew_status = 0;
        $subscription->cancellation_date = now();
        $subscription->save();
    }

    public function renewDueSubscriptions(int $limit = 100): array
    {
        $now = now();
        $items = SubscriptionOrder::query()
            ->where('pay_type', Payment::PAY_CHANNEL_ALIPAY)
            ->whereIn('status', ['active', 'failed_to_renew'])
            ->where('auto_renew_status', 1)
            ->where('agreement_no', '!=', '')
            ->where('deduct_fail_count', '<', (int)config('pay.alipay_agreement.max_deduct_fail_count', 3))
            ->where(function ($query) use ($now) {
                $query->whereNull('next_pay_date')->orWhere('next_pay_date', '<=', $now);
            })
            ->limit($limit)
            ->get();

        $success = 0;
        $failed = 0;
        foreach ($items as $subscription) {
            try {
                $order = $this->createRenewalOrder($subscription);
                $config = Payment::getDefaultAlipayConfigByAppId($order->app_id, $order->order_no);
                $this->payByAgreement($order, $subscription, $config, false);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $this->recordDeductFailure($subscription, $e->getMessage());
                Log::error('支付宝自动续费扣款失败', [
                    'subscription_id' => $subscription->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return compact('success', 'failed');
    }

    /**
     * @throws ApiException
     */
    private function payByAgreement(MemberOrder $order, SubscriptionOrder $subscription, array $paymentConfig, bool $isInitialPay): void
    {
        if ($order->pay_status === MemberOrder::PAY_STATUS_PAID) {
            return;
        }

        $product = MemberProduct::query()->where('id', $order->product_id)->first();
        if (!$product) {
            throw new ApiException('订阅商品不存在');
        }

        $amount = $isInitialPay ? (float)$order->member_price : $this->renewalAmount($product);
        if ($amount < 0.01) {
            throw new ApiException('扣款金额不能小于0.01元');
        }

        $payClient = Pay::alipay(Payment::tidyForPayConfig('alipay', $paymentConfig));
        $result = $payClient->pay($payClient->mergeCommonPlugins([
            \Yansongda\Pay\Plugin\Alipay\V2\Pay\Agreement\Pay\PayPlugin::class,
        ]), [
            'out_trade_no' => $order->order_no,
            'subject' => $isInitialPay ? '会员自动续费首期扣款' : '会员自动续费',
            'total_amount' => $amount,
            'product_code' => config('pay.alipay_agreement.pay_product_code', self::DEFAULT_PAY_PRODUCT_CODE),
            'agreement_params' => [
                'agreement_no' => $subscription->agreement_no,
            ],
        ]);

        $tradeNo = (string)($result['trade_no'] ?? '');
        if ($tradeNo === '') {
            throw new ApiException('支付宝扣款成功但未返回交易号');
        }

        app(PaymentService::class)->paySuccessful($order->order_no, $tradeNo, $amount, $paymentConfig);

        $order->subscription_id = $subscription->id;
        $order->is_subscribe = 1;
        $order->save();

        $expiresDate = Carbon::createFromTimestamp(User::query()->where('id', $order->user_id)->value('overdue_time') ?: time());
        $subscription->pay_amount = round((float)$subscription->pay_amount + $amount, 2);
        $subscription->subscribe_success_count = (int)$subscription->subscribe_success_count + 1;
        $subscription->deduct_fail_count = 0;
        $subscription->status = 'active';
        $subscription->agreement_status = $subscription->agreement_status ?: 'NORMAL';
        $subscription->last_pay_date = now();
        $subscription->purchase_date = $subscription->purchase_date ?: now();
        $subscription->expires_date = $expiresDate;
        $subscription->renewal_date = $expiresDate;
        $subscription->next_pay_date = $expiresDate;
        $subscription->save();
    }

    private function ensurePendingSubscription(MemberOrder $order, MemberProduct $product): SubscriptionOrder
    {
        return DB::transaction(function () use ($order, $product) {
            $subscription = SubscriptionOrder::query()
                ->where('pay_type', Payment::PAY_CHANNEL_ALIPAY)
                ->where('remark', $order->order_no)
                ->lockForUpdate()
                ->first();

            if (!$subscription) {
                $subscription = new SubscriptionOrder;
                $subscription->app_id = $order->app_id;
                $subscription->user_id = $order->user_id;
                $subscription->original_transaction_id = '';
                $subscription->agreement_no = '';
                $subscription->external_agreement_no = $this->externalAgreementNo($order->order_no);
                $subscription->product_id = $product->pay_product_id ?: (string)$product->id;
                $subscription->pay_type = Payment::PAY_CHANNEL_ALIPAY;
                $subscription->currency = $order->currency ?: 'CNY';
                $subscription->status = 'pending';
                $subscription->agreement_status = '';
                $subscription->auto_renew_status = 0;
                $subscription->pay_amount = 0;
                $subscription->purchase_date = now();
                $subscription->expires_date = now();
                $subscription->renewal_date = now();
                $subscription->remark = $order->order_no;
                $subscription->save();
            }

            $order->subscription_id = $subscription->id;
            $order->is_subscribe = 1;
            $order->save();

            return $subscription;
        });
    }

    private function buildSignParams(MemberOrder $order, MemberProduct $product, SubscriptionOrder $subscription, array $payConfig): array
    {
        $periodRuleParams = [
            'period_type' => $this->periodType($product),
            'period' => $this->periodValue($product),
            'execute_time' => now()->addDay()->format('Y-m-d'),
            'single_amount' => sprintf('%.2f', $this->renewalAmount($product)),
            'total_amount' => sprintf('%.2f', $this->renewalAmount($product)),
        ];
        $totalPayments = (int)config('pay.alipay_agreement.total_payments', 0);
        if ($totalPayments > 0) {
            $periodRuleParams['total_payments'] = $totalPayments;
        }

        return [
            '_method' => 'GET',
            '_return_url' => $payConfig['return_url'],
            '_notify_url' => $payConfig['notify_url'],
            'personal_product_code' => config('pay.alipay_agreement.sign_product_code', self::DEFAULT_SIGN_PRODUCT_CODE),
            'sign_scene' => config('pay.alipay_agreement.sign_scene', self::DEFAULT_SIGN_SCENE),
            'external_agreement_no' => $subscription->external_agreement_no,
            'access_params' => [
                'channel' => 'ALIPAYAPP',
            ],
            'period_rule_params' => $periodRuleParams,
            'sign_notify_url' => $payConfig['notify_url'],
            'memo' => $order->product_name ?: '会员自动续费',
        ];
    }

    private function signPagePlugins(): array
    {
        return [
            StartPlugin::class,
            SignPlugin::class,
            FormatPayloadBizContentPlugin::class,
            AddPayloadSignaturePlugin::class,
            AddRadarPlugin::class,
            ResponseHtmlPlugin::class,
            ParserPlugin::class,
        ];
    }

    private function createRenewalOrder(SubscriptionOrder $subscription): MemberOrder
    {
        $unpaidOrder = MemberOrder::query()
            ->where('subscription_id', $subscription->id)
            ->where('pay_type', Payment::PAY_CHANNEL_ALIPAY)
            ->where('pay_status', MemberOrder::PAY_STATUS_UNPAID)
            ->orderBy('id', 'desc')
            ->first();

        if ($unpaidOrder) {
            return $unpaidOrder;
        }

        $product = MemberProduct::query()
            ->where('pay_product_id', $subscription->product_id)
            ->where('app_id', $subscription->app_id)
            ->where('is_enable', 1)
            ->first();

        if (!$product) {
            $product = MemberProduct::query()
                ->where('id', $subscription->product_id)
                ->where('app_id', $subscription->app_id)
                ->where('is_enable', 1)
                ->firstOrFail();
        }

        $orderNo = generateOrderNo($subscription->user_id);

        return MemberOrder::query()->create([
            'app_id' => $subscription->app_id,
            'mch_id' => '',
            'product_id' => $product->id,
            'subscription_id' => $subscription->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'user_id' => $subscription->user_id,
            'type' => MemberOrder::TYPE_SUBSCRIBE,
            'order_no' => $orderNo,
            'member_type' => $product->validity_type,
            'quantity' => $product->validity,
            'pay_type' => Payment::PAY_CHANNEL_ALIPAY,
            'pay_source' => Payment::PAY_TYPE_APP,
            'pay_status' => MemberOrder::PAY_STATUS_UNPAID,
            'member_status' => 'not_ordered',
            'member_price' => $this->renewalAmount($product),
            'is_subscribe' => 1,
            'currency' => $subscription->currency ?: 'CNY',
        ]);
    }

    private function recordDeductFailure(SubscriptionOrder $subscription, string $reason): void
    {
        $subscription->subscribe_fail_count = (int)$subscription->subscribe_fail_count + 1;
        $subscription->deduct_fail_count = (int)$subscription->deduct_fail_count + 1;
        $subscription->subscribe_fail_reason = mb_substr($reason, 0, 250);
        $subscription->status = 'failed_to_renew';
        $subscription->next_pay_date = now()->addDay();
        $subscription->save();
    }

    private function renewalAmount(MemberProduct $product): float
    {
        return round((float)($product->renewal_price > 0 ? $product->renewal_price : $product->price), 2);
    }

    private function periodType(MemberProduct $product): string
    {
        return match ($product->pay_cycle) {
            MemberProduct::TimeTypeWeek, MemberProduct::TimeTypeDay => 'DAY',
            default => 'MONTH',
        };
    }

    private function periodValue(MemberProduct $product): int
    {
        $value = max(1, (int)$product->pay_cycle_val);

        return match ($product->pay_cycle) {
            MemberProduct::TimeTypeWeek => $value * 7,
            MemberProduct::TimeTypeYear => $value * 12,
            default => $value,
        };
    }

    private function externalAgreementNo(string $orderNo): string
    {
        return 'ALIAGR' . $orderNo;
    }

    private function extractAlipayQueryFromHtml(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES);

        if (preg_match('/url=\'([^\']+)\'/', $decoded, $matches)) {
            return $matches[1];
        }

        return $html;
    }
}
