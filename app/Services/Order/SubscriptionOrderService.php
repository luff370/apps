<?php

namespace App\Services\Order;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Services\Service;
use App\Models\MemberOrder;
use App\Support\Utils\Apple;
use App\Models\SubscriptionLog;
use App\Models\SubscriptionOrder;
use App\Dao\Order\SubscriptionOrderDao;

class SubscriptionOrderService extends Service
{
    public function __construct(SubscriptionOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 处理接收到的苹果订阅通知
     *
     * @param string $data JSON 编码的通知数据
     *
     * @return array 包含处理结果的数组
     */
    public function processNotification(string $data): array
    {
        // 检查是否收到数据
        if (!$data) {
            logger()->error("subscription-order-notification-error:Invalid signature");

            return $this->generateErrorResponse("No data received");
        }

        // 解码并验证签名
        $payload = $this->decodeAndVerifySignature($data);

        // 如果解码失败，返回错误响应
        if (!$payload) {
            logger()->error("subscription-order-notification-error:Invalid signature", ['payload' => $payload]);

            return $this->generateErrorResponse("Invalid signature");
        }

        try {
            // 处理通知类型
            return $this->handleNotificationType($payload);
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage());

            return $this->generateErrorResponse($exception->getMessage());
        }
    }

    /**
     * 解码并验证签名
     *
     * @param string $jwt 签名的有效载荷
     *
     * @return array|null 解码后的有效载荷数组或 null（如果验证失败）
     */
    private function decodeAndVerifySignature($jwt)
    {
        try {
            $decodedPayload = \Readdle\AppStoreServerAPI\ResponseBodyV2::createFromRawNotification(
                $jwt,
                \Readdle\AppStoreServerAPI\Util\Helper::toPEM(file_get_contents('https://www.apple.com/certificateauthority/AppleRootCA-G3.cer'))
            );

            return json_decode(json_encode($decodedPayload), true);
        } catch (Exception $e) {
            // 处理解码或验证失败的情况
            logger()->error($e);

            return null;
        }
    }

    /**
     * 根据通知类型处理通知
     * array:6 [
     * "notificationType" => "DID_CHANGE_RENEWAL_STATUS"
     * "subtype" => "AUTO_RENEW_ENABLED"
     * "notificationUUID" => "33dee81d-3e83-40a5-a76c-a181bc30fc6f"
     * "appMetadata" => array:7 [
     * "appAppleId" => "1538323124"
     * "bundleId" => "com.aysd.tuner"
     * "bundleVersion" => "2"
     * "environment" => "Sandbox"
     * "renewalInfo" => array:14 [
     * "autoRenewProductId" => "online_MONEY_2"
     * "autoRenewStatus" => 1
     * "environment" => "Sandbox"
     * "expirationIntent" => null
     * "gracePeriodExpiresDate" => null
     * "isInBillingRetryPeriod" => null
     * "offerIdentifier" => null
     * "offerType" => null
     * "originalTransactionId" => "2000000693085074"
     * "priceIncreaseStatus" => null
     * "productId" => "online_MONEY_2"
     * "recentSubscriptionStartDate" => 1724567455000
     * "renewalDate" => 1724571055000
     * "signedDate" => 1724569923361
     * ]
     * "transactionInfo" => array:26 [
     * "appAccountToken" => null
     * "bundleId" => "com.aysd.tuner"
     * "currency" => "CNY"
     * "environment" => "Sandbox"
     * "expiresDate" => 1724571055000
     * "inAppOwnershipType" => "PURCHASED"
     * "isUpgraded" => null
     * "offerDiscountType" => null
     * "offerIdentifier" => null
     * "offerType" => null
     * "originalPurchaseDate" => 1724337197000
     * "originalTransactionId" => "2000000693085074"
     * "price" => 198000
     * "productId" => "online_MONEY_2"
     * "purchaseDate" => 1724567455000
     * "quantity" => 1
     * "revocationDate" => null
     * "revocationReason" => null
     * "signedDate" => 1724569923361
     * "storefront" => "CHN"
     * "storefrontId" => "143465"
     * "subscriptionGroupIdentifier" => "21527583"
     * "transactionId" => "2000000694994341"
     * "transactionReason" => "PURCHASE"
     * "type" => "Auto-Renewable Subscription"
     * "webOrderLineItemId" => "2000000071908298"
     * ]
     * "status" => 1
     * ]
     * "version" => "2.0"
     * "signedDate" => 1724569923380
     * ]
     *
     * @param array $payload 解码后的有效载荷数组
     */
    private function handleNotificationType(array $payload): array
    {
        logger()->info("subscription-order-notification-handle", ['payload' => $payload]);

        // 获取通知类型和交易信息
        $notificationType = $payload['notificationType'];
        $transactionInfo = array_merge($payload['appMetadata']['transactionInfo'], $payload['appMetadata']['renewalInfo']);
        $transactionInfo['notification_uuid'] = $payload['notificationUUID'];
        $transactionInfo['notification_type'] = $notificationType;
        $transactionInfo['subtype'] = $payload['subtype'] ?? '';
        $isTrial = $transactionInfo['isTrialPeriod'] ?? false;

        // 根据通知类型执行相应的处理逻辑
        switch ($notificationType) {
            case 'DID_RENEW': // 处理 DID_RENEW 通知（订阅续订成功）
                $this->processDidRenew($transactionInfo);
                break;

            case 'DID_FAIL_TO_RENEW': // 处理 DID_FAIL_TO_RENEW 通知（订阅续订失败）
                $this->processDidFailToRenew($transactionInfo);
                break;

            case 'CANCEL': // 处理 CANCEL 通知（订阅取消）
                $this->processCancel($transactionInfo);
                break;

            case 'DID_RECOVER': // 处理 DID_RECOVER 通知（订阅恢复）
                $this->processDidRecover($transactionInfo);
                break;

            case 'INITIAL_BUY': // 处理 INITIAL_BUY 通知（首次购买）
                $this->processInitialBuy($transactionInfo, $isTrial);
                break;

            case 'INTERACTIVE_RENEWAL': // 处理 INTERACTIVE_RENEWAL 通知（互动续订）
                $this->processInteractiveRenewal($transactionInfo);
                break;

            case 'DID_CHANGE_RENEWAL_PREF': // 处理 DID_CHANGE_RENEWAL_PREF 通知（自动续订偏好更改）
                $this->processDidChangeRenewalPref($transactionInfo);
                break;

            case 'DID_CHANGE_RENEWAL_STATUS': // 处理 DID_CHANGE_RENEWAL_STATUS 通知（自动续订状态更改）
                $this->processDidChangeRenewalStatus($transactionInfo);
                break;

            case 'REVOKE': // 处理 REVOKE 通知（订阅撤销）
                $this->processRevoke($transactionInfo);
                break;

            case 'DID_CONVERT_TRIAL_TO_PAID': //  处理 DID_CONVERT_TRIAL_TO_PAID 通知（免费试用转为付费）
                $this->processDidConvertTrialToPaid($transactionInfo);
                break;

            default:
                // 如果通知类型未知，返回错误响应
                return $this->generateErrorResponse("Unknown notification type: " . $notificationType);
        }

        // 成功处理后返回成功响应
        return ['status' => 'success', 'message' => 'Notification processed successfully'];
    }

    /**
     * 处理 DID_RENEW 通知（订阅续订成功）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidRenew(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态和过期时间
        $subscription->update([
            'status' => 'active',
            'expires_date' => $this->parseTime($transactionInfo['expiresDate']),
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
        ]);

        // 创建新的订单记录
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'active');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 DID_FAIL_TO_RENEW 通知（订阅续订失败）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidFailToRenew(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 处理解码后的续订信息
        $expirationIntent = $transactionInfo['expirationIntent'] ?? null;
        $isInBillingRetryPeriod = $transactionInfo['isInBillingRetryPeriod'] ?? null;

        // 根据 expirationIntent 进行续订失败的原因分析
        $failedReason = Apple::getExpirationReason($expirationIntent);

        // 如果用户仍处于重试续订期
        if ($isInBillingRetryPeriod) {
            $remark = "用户仍处于重试续订期，Apple 将继续尝试处理付款。\n";
        } else {
            $remark = "Apple 不再尝试续订，订阅已彻底失败。\n";
        }

        // 更新订阅的状态为续订失败
        $subscription->update([
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
            'status' => 'failed_to_renew',
            'subscribe_fail_reason' => $failedReason,
            'remark' => $remark,
        ]);

        // 创建新的订单记录，标记为取消状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'failed_to_renew', $remark, $failedReason);
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 CANCEL 通知（订阅取消）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processCancel(array $transactionInfo): void
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为取消
        $subscription->update([
            'status' => 'canceled',
        ]);

        // 创建新的订单记录，标记为取消状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'canceled');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 DID_RECOVER 通知（订阅恢复）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidRecover(array $transactionInfo): void
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为活跃，并更新过期时间
        $subscription->update([
            'status' => 'active',
            'expires_date' => $this->parseTime($transactionInfo['expiresDate']),
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
        ]);

        // 创建新的订单记录，标记为完成状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'active');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 INITIAL_BUY 通知（首次购买）
     *
     * @param array $transactionInfo 交易信息
     * @param bool $isTrial 是否为免费试用
     */
    private function processInitialBuy(array $transactionInfo, bool $isTrial): void
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为活跃，并更新过期时间
        $subscription->update([
            'subscribe_product_id' => $transactionInfo['productId'],
            'status' => $isTrial ? 'trial' : 'active',
            'purchase_date' => $this->parseTime($transactionInfo['purchaseDate']),
            'expires_date' => $this->parseTime($transactionInfo['expiresDate']),
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
            'is_trial_period' => $isTrial,
        ]);

        // 创建新的订单记录，标记为完成状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, $isTrial ? 'trial' : 'active');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 INTERACTIVE_RENEWAL 通知（互动续订）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processInteractiveRenewal(array $transactionInfo): void
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为活跃，并更新过期时间
        $subscription->update([
            'status' => 'active',
            'expires_date' => $this->parseTime($transactionInfo['expiresDate']),
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
        ]);

        // 创建新的订单记录，标记为完成状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'active');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 DID_CHANGE_RENEWAL_PREF 通知（自动续订偏好更改）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidChangeRenewalPref(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        $subscription->update([
            'auto_renew_status' => $transactionInfo['autoRenewStatus'],
            'auto_renew_preference' => $transactionInfo['autoRenewPreference'] ?? '',
        ]);
    }

    /**
     * 处理 DID_CHANGE_RENEWAL_STATUS 通知（自动续订状态更改）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidChangeRenewalStatus(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态，取决于自动续订状态
        $subscription->update([
            'status' => $transactionInfo['autoRenewStatus'] ? 'active' : 'canceled',
            'auto_renew_status' => $transactionInfo['autoRenewStatus'],
            'auto_renew_preference' => $transactionInfo['autoRenewPreference'] ?? '',
        ]);

        // 创建新的订单记录
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, $transactionInfo['autoRenewStatus'] ? 'active' : 'canceled');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 REVOKE 通知（订阅撤销）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processRevoke(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为撤销
        $subscription->update([
            'status' => 'revoked',
        ]);

        // 创建新的订单记录，标记为撤销状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'revoked');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    /**
     * 处理 DID_CONVERT_TRIAL_TO_PAID 通知（免费试用转为付费）
     *
     * @param array $transactionInfo 交易信息
     */
    private function processDidConvertTrialToPaid(array $transactionInfo)
    {
        // 查找对应的订阅
        $subscription = $this->getOrderInfo($transactionInfo['originalTransactionId']);

        // 更新订阅的状态为活跃，并取消试用期
        $subscription->update([
            'paid' => true,
            'status' => 'active',
            'is_trial_period' => false,
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
        ]);

        // 创建新的订单记录，标记为完成状态
        $this->saveSubscriptionLog($subscription['user_id'], $transactionInfo, 'active');
        // 更新会员状态
        $this->updateMemberInfo($transactionInfo);
    }

    private function getOrderInfo($originalTransactionId)
    {
        return SubscriptionOrder::query()->where('original_transaction_id', $originalTransactionId)->firstOrFail();
    }

    // 保存订阅记录
    private function saveSubscriptionLog(int $userId, array $transactionInfo, string $status, $remark = '', $failedReason = ''): void
    {
        SubscriptionLog::query()->create([
            'user_id' => $userId,
            'notification_uuid' => $transactionInfo['notification_uuid'],
            'notification_type' => $transactionInfo['notification_type'],
            'auto_renew_status' => $transactionInfo['autoRenewStatus'],
            'expiration_intent' => $transactionInfo['expirationIntent'] ?? 0,
            'sub_type' => $transactionInfo['subtype'],
            'transaction_id' => $transactionInfo['transactionId'],
            'original_transaction_id' => $transactionInfo['originalTransactionId'],
            'product_id' => $transactionInfo['productId'],
            'purchase_date' => $this->parseTime($transactionInfo['purchaseDate']),
            'expires_date' => $this->parseTime($transactionInfo['expiresDate']),
            'renewal_date' => $this->parseTime($transactionInfo['renewalDate'] ?? null),
            'grace_period_expires_date' => $this->parseTime($transactionInfo['gracePeriodExpiresDate'] ?? null),
            'subscribe_fail_reason' => $failedReason,
            'remark' => $remark,
            'quantity' => 1,
            'status' => $status,
        ]);
    }

    private function updateMemberInfo(array $transactionInfo)
    {
        // // 处理解码后的续订信息
        // $tradeNo = $transactionInfo['originalTransactionId'];
        // $expirationDate = Carbon::createFromTimestampMs($transactionInfo['expiresDate']);
        // $expirationIntent = $transactionInfo['expirationIntent'] ?? null;
        // $isInBillingRetryPeriod = $transactionInfo['isInBillingRetryPeriod'] ?? null;
        // $cancellationDate = $transactionInfo['cancellationDate'] ?? null;
        // $isTrialPeriod = $transactionInfo['isTrialPeriod'] ?? null;
        //
        // // 支付状态
        // $paymentStatus = Apple::determinePaymentStatus($expirationDate->unix(), $isTrialPeriod, $isInBillingRetryPeriod, $expirationIntent);
        // // 会员状态
        // $membershipStatus = Apple::determineMembershipStatus($expirationDate->unix(), $cancellationDate, $isTrialPeriod);
        //
        // // 更新用户的订阅信息
        // $memberOrder = MemberOrder::query()->where('trade_no', $tradeNo)->first();
        // $memberOrder->expires_date = $expirationDate;
        // $memberOrder->pay_status = $paymentStatus;
        // $memberOrder->member_status = $membershipStatus;
        // $memberOrder->save();
        //
        // // 更新用户的会员状态
        // $user = User::query()->where('id', $memberOrder->user_id)->first();
        // if ($user) {
        //     $user->is_vip = in_array($membershipStatus, ['trial', 'active']);
        //     $user->vip_type = $isTrialPeriod == 'true' ? 3 : 1;
        //     $user->overdue_time = $expirationDate->unix();
        //     $user->expires_date = $expirationDate;
        //
        //     $user->save();
        // }
    }

    /**
     * 生成错误响应
     *
     * @param string $message 错误消息
     *
     * @return array 包含错误信息的数组
     */
    private function generateErrorResponse(string $message)
    {
        return [
            'status' => 'error',
            'status_code' => 400,
            'message' => $message,
        ];
    }

    private function parseTime($time)
    {
        if (!$time || $time == 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $time / 1000);
    }

    public function tidyListData($list)
    {
        $payTypeMap = MemberOrder::payTypeMap();
        $subscribeStatusMap = SubscriptionOrder::subscribeStatusMap();
        $subscribeStatusColorMap = SubscriptionOrder::subscribeStatusColorMap();
        foreach ($list as &$item) {
            $item['subscribe_status_name'] = $subscribeStatusMap[$item['status']] ?? '未订阅';
            $item['subscribe_status_color'] = $subscribeStatusColorMap[$item['status']] ?? 'default';
            $item['pay_type_name'] = $payTypeMap[$item['pay_type']] ?? '';
        }

        return $list;
    }
}

