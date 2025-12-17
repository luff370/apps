<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use Yansongda\Pay\Pay;
use App\Models\MemberOrder;
use Illuminate\Http\Request;
use App\Support\Utils\Apple;
use App\Exceptions\ApiException;
use App\Models\SubscriptionOrder;
use App\Support\Services\Payment;
use App\Exceptions\AdminException;
use Illuminate\Support\Facades\Log;
use App\Services\Order\PaymentService;
use App\Services\Order\SubscriptionOrderService;

class PayCallbackController extends Controller
{
    public function __construct(PaymentService $paymentService)
    {
        $this->service = $paymentService;
    }

    public function googlePayVerify(Request $request)
    {
        $req = $request->all();
        $appId = $this->getAppId();

        $configLocation = storage_path() . '/cert/google-services.json';
        // 将 JSON 设置 环境变量
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $configLocation);
        // 变量  包名
        $package_name = $req['packageName'];
        // 变量  设置的商品ID
        $product_id = $req['productId'];
        // 变量  客户端传过来的 purchaseToken
        $purchase_token = $req['purchaseToken'];
        // 订单号
        $order_no = $req['orderNo'];

        try {
            $google_client = new \Google_Client;
            $google_client->useApplicationDefaultCredentials();
            $google_client->addScope(\Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
            $androidPublishService = new \Google_Service_AndroidPublisher($google_client);
            $result = $androidPublishService->purchases_products->get(
                $package_name,
                $product_id,
                $purchase_token
            );
            logger()->info('-----google verify-------', [$req, $result]);

            //purchaseState  0。已购买1.已取消2.待定 consumptionState 0。尚未消耗1.已消耗
            if ($result->purchaseState == 0 && $result->consumptionState == 1) {
                // 支付验证成功 业务处理
                $res = $this->service->paySuccess($order_no, $result->getOrderId(), $package_name, $product_id);

                return $this->success(['pay_success' => $res]);
            }
        } catch (\Exception $exception) {
            logger()->error('google 支付验证失败：' . $exception->getMessage());
        }

        return $this->success(['pay_success' => false]);
    }

    public function applePayVerify(Request $request)
    {
        $orderNo = $request->get('orderNo');
        $payload = $request->get('receipt');
        logger()->info('---苹果支付验证---', $request->all());

        $response = Apple::validateReceipt($payload)->json();
        logger()->info('---苹果支付验证结果---', $response);

        // 支付验证成功
        if ($response['status'] == 0) {
            return $this->processSubscription($response, $orderNo);
        }

        return $this->success(['pay_success' => false, 'validate_res' => $response]);
    }

    private function processSubscription($data, $orderNo)
    {
        // $latestReceiptInfo = [
        //     "quantity" => "1",
        //     "product_id" => "online_MONEY_2",
        //     "transaction_id" => "2000000696201557",
        //     "original_transaction_id" => "2000000693085074",
        //     "purchase_date" => "2024-08-26 17:32:12 Etc/GMT",
        //     "purchase_date_ms" => "1724693532000",
        //     "purchase_date_pst" => "2024-08-26 10:32:12 America/Los_Angeles",
        //     "original_purchase_date" => "2024-08-22 14:33:17 Etc/GMT",
        //     "original_purchase_date_ms" => "1724337197000",
        //     "original_purchase_date_pst" => "2024-08-22 07:33:17 America/Los_Angeles",
        //     "expires_date" => "2024-08-26 18:08:12 Etc/GMT",
        //     "expires_date_ms" => "1724695692000",
        //     "expires_date_pst" => "2024-08-26 11:08:12 America/Los_Angeles",
        //     "web_order_line_item_id" => "2000000072067078",
        //     "is_trial_period" => "false",
        //     "is_in_intro_offer_period" => "false",
        //     "in_app_ownership_type" => "PURCHASED",
        //     "subscription_group_identifier" => "21527583",
        // ];
        // $pendingRenewal =  [
        //   "expiration_intent" => "1"
        //   "auto_renew_product_id" => "online_MONEY_2"
        //   "is_in_billing_retry_period" => "0"
        //   "product_id" => "online_MONEY_2"
        //   "original_transaction_id" => "2000000701346125"
        //   "auto_renew_status" => "0"
        // ]

        try {
            $latestReceiptInfo = end($data['latest_receipt_info']);
            $pendingRenewal = end($data['pending_renewal_info']);

            // 订阅记录
            $subscription = SubscriptionOrder::query()->where('original_transaction_id', $latestReceiptInfo['original_transaction_id'])->first();

            // 判断当前订阅是否已处理
            $otherOrderCount = MemberOrder::query()->where('trade_no', $latestReceiptInfo['original_transaction_id'])->where('order_no', '!=', $orderNo)->count();
            if ($otherOrderCount && $subscription) {
                return $this->success(['pay_success' => true]);
            }

            $memberOrder = MemberOrder::query()->where('order_no', $orderNo)->first();
            if (!$memberOrder) {
                return $this->fail("订单号不存在");
            }

            // 是否沙箱环境
            $isSandbox = $data['environment'] == 'Sandbox';
            // 自动续订状态
            $autoRenewStatus = $pendingRenewal['auto_renew_status'] ?? 0;
            // 订阅过期状态
            $expirationIntent = $pendingRenewal['expiration_intent'] ?? 0;
            $expirationReason = Apple::getExpirationReason($expirationIntent);
            // 获取订阅的过期时间
            $expirationDateMs = $latestReceiptInfo['expires_date_ms'];
            $expirationDate = Carbon::createFromTimestampMs($expirationDateMs);
            // 取消时间
            $cancellationDate = $pendingRenewal['cancellation_date'] ?? null;
            // 试用状态
            $isTrialPeriod = $latestReceiptInfo['is_trial_period'] ?? false;
            // 订阅失败重试状态
            $isInBillingRetryPeriod = $pendingRenewal['is_in_billing_retry_period'] ?? false;
            // 订阅状态
            $subscriptionStatus = Apple::determineSubscriptionStatus(
                $expirationDateMs,
                $cancellationDate,
                $isTrialPeriod,
                $isInBillingRetryPeriod,
                $pendingRenewal
            );
            // 支付状态
            $paymentStatus = Apple::determinePaymentStatus($expirationDate->unix(), $isTrialPeriod, $isInBillingRetryPeriod, $expirationIntent);
            // 会员状态
            $membershipStatus = Apple::determineMembershipStatus($expirationDate->unix(), $cancellationDate, $isTrialPeriod);

            // 更新用户的订阅信息
            $memberOrder->pay_type = 'apple';
            $memberOrder->pay_source = $this->getPlatform();
            $memberOrder->pay_time = time();
            $memberOrder->is_subscribe = 1;
            $memberOrder->trade_no = $latestReceiptInfo['original_transaction_id'];
            $memberOrder->vip_day = Carbon::parse($latestReceiptInfo['expires_date'])->diffInDays(Carbon::parse($latestReceiptInfo['purchase_date']));
            $memberOrder->purchase_date = Carbon::parse($latestReceiptInfo['original_purchase_date']);
            $memberOrder->expires_date = Carbon::parse($latestReceiptInfo['expires_date']);
            $memberOrder->pay_status = $paymentStatus;
            $memberOrder->member_status = $membershipStatus;
            $memberOrder->save();

            // 更新用户的会员状态
            $user = User::query()->where('id', $memberOrder->user_id)->first();
            if ($user) {
                $user->is_vip = in_array($membershipStatus, ['trial', 'active']);
                $user->vip_type = $isTrialPeriod == 'true' ? 3 : 1;
                $user->expires_date = $expirationDate;
                $user->overdue_time = $expirationDate->unix();

                $user->save();
            }

            // 订阅数据记录
            if (!$subscription) {
                // 创建新的订阅
                $subscription = new SubscriptionOrder;
                $subscription->app_id = $memberOrder->app_id;
                $subscription->user_id = $memberOrder['user_id'];
                $subscription->original_transaction_id = $latestReceiptInfo['original_transaction_id'];
                $subscription->product_id = $latestReceiptInfo['product_id'];
                $subscription->pay_type = 'apple';
                $subscription->currency = $memberOrder['currency'];
                $subscription->is_sandbox = $isSandbox;
            }
            $subscription->auto_renew_status = $autoRenewStatus;
            $subscription->is_trial_period = $isTrialPeriod == 'true';
            $subscription->status = $subscriptionStatus;
            $subscription->purchase_date = Carbon::parse($latestReceiptInfo['original_purchase_date']);
            $subscription->expires_date = $expirationDate;
            $subscription->renewal_date = $expirationDate;
            $subscription->cancellation_date = $cancellationDate;
            $subscription->subscribe_fail_reason = $expirationReason;
            $subscription->latest_receipt = $data['latest_receipt'];
            $subscription->save();

            return $this->success(['pay_success' => true]);
        } catch (\Exception $exception) {
            logger()->error('apple支付验证处理失败：', ['data' => $data, 'exception' => $exception]);

            return $this->fail('支付处理失败');
        }
    }

    public function appleNotify(Request $request, SubscriptionOrderService $service)
    {
        $data = $request->getContent();
        logger()->info("subscription-order-notification", ['data' => $data]);

        $response = $service->processNotification($data);

        if ($response['status'] === 'success') {
            return response()->json(['message' => $response['message']], 200);
        } else {
            return response()->json(['message' => $response['message']], $response['status_code']);
        }
    }

    /**
     * @throws ApiException
     */
    public function wechatNotify($id)
    {
        Log::info("WeChatNotify data:", request()->all());
        Log::info("WeChatNotify header:", request()->header());

        $config = Payment::getWechatConfigByPaymentId($id);
        $wechat = Pay::wechat(Payment::tidyForPayConfig('wechat', $config));
        try {
            $data = $wechat->callback();                  // 自动验签并解析参数
            // Log::info('WeChatNotify:', $data->toArray());

            $resource = $data['resource']['ciphertext'];
            Log::info('微信支付回调数据', $resource);

            // 校验支付状态
            if ($resource['trade_state'] !== 'SUCCESS') {
                Log::warning("微信支付状态异常：{$resource['trade_state']}");

                return response()->json(['message' => 'Invalid trade_state'], 400);
            }

            $orderNo = $resource['out_trade_no'];
            $transactionId = $resource['transaction_id'];
            $payAmount = $resource['amount']['total'] / 100; // 单位是分，需要转换

            $this->service->paySuccessful($orderNo, $transactionId, $payAmount, $config);

            return $wechat->success(); // 响应微信成功
        } catch (\Exception $e) {
            Log::error('WeChat Notify Error:', ['message' => $e->getMessage()]);

            return response('fail', 400);
        }
    }

    /**
     * @throws AdminException
     */
    public function alipayNotify($id)
    {
        Log::info("AlipayNotify data:", request()->all());

        $config = Payment::getAlipayConfigByPaymentId($id);
        $alipay = Pay::alipay(Payment::tidyForPayConfig('alipay', $config));

        try {
            $raw = request()->except(['s']);
            $data = $alipay->callback($raw); // 自动验签并解析参数
            // Log::info('AlipayNotify:', $data->toArray());

            // 验证支付状态
            if (!in_array($data['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
                Log::warning("支付宝支付状态异常：{$data['trade_status']}");

                return response()->json(['message' => 'Invalid trade_status'], 400);
            }

            $orderNo = $data['out_trade_no'];
            $tradeNo = $data['trade_no'];
            $totalAmount = $data['total_amount'];

            // 处理订单逻辑
            $this->service->paySuccessful($orderNo, $tradeNo, $totalAmount, $config);

            return $alipay->success(); // 响应支付宝成功
        } catch (\Exception $e) {
            Log::error('Alipay Notify Error:', ['message' => $e->getMessage()]);

            return response('fail', 400);
        }
    }
}
