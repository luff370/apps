<?php

namespace App\Support\Utils;

use App\Models\MemberProduct;
use GuzzleHttp\Client;
use Azimo\Apple\Auth\Jwt\JwtParser;
use Azimo\Apple\Api\AppleApiClient;
use Illuminate\Support\Facades\Http;
use Azimo\Apple\Auth\Jwt\JwtVerifier;
use Azimo\Apple\Auth\Jwt\JwtValidator;
use Azimo\Apple\Api\Factory\ResponseFactory;
use Azimo\Apple\Auth\Factory\AppleJwtStructFactory;
use Azimo\Apple\Auth\Service\AppleJwtFetchingService;

class Apple
{
    public static function tokenValidate($verifyToken, $package): \Azimo\Apple\Auth\Struct\JwtPayload
    {
        $appleJwtFetchingService = new AppleJwtFetchingService(
            new JwtParser(new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder)),
            new JwtVerifier(
                new AppleApiClient(
                    new Client(
                        [
                            'base_uri' => 'https://appleid.apple.com',
                            'timeout' => 5,
                            'connect_timeout' => 5,
                        ]
                    ),
                    new ResponseFactory
                ),
                new \Lcobucci\JWT\Validation\Validator,
                new \Lcobucci\JWT\Signer\Rsa\Sha256
            ),
            new JwtValidator(
                new \Lcobucci\JWT\Validation\Validator,
                [
                    new \Lcobucci\JWT\Validation\Constraint\IssuedBy('https://appleid.apple.com'),
                    new \Lcobucci\JWT\Validation\Constraint\PermittedFor($package),
                ]
            ),

            new AppleJwtStructFactory
        );

        return $appleJwtFetchingService->getJwtPayload($verifyToken);
    }

    public static function payValidate($receipt_data, $ios_sandBox)
    {
        /**
         * 21000 App Store不能读取你提供的JSON对象
         * 21002 receipt-data域的数据有问题
         * 21003 receipt无法通过验证
         * 21004 提供的shared secret不匹配你账号中的shared secret
         * 21005 receipt服务器当前不可用
         * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
         * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
         * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
         */
        if ($ios_sandBox) {    //沙盒购买地址
            $url = "https://sandbox.itunes.apple.com/verifyReceipt";
        } else {  //正式购买地址
            $url = "https://buy.itunes.apple.com/verifyReceipt";
        }

        $post_data = ["receipt-data" => $receipt_data, "password" => config('pay.apple.app_shared_secret')];
        $response = Http::post($url, $post_data);

        return $response->json();
    }

    /**
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     */
    public static function validateReceipt($receiptData, $oldTransaction = false): \Illuminate\Http\Client\Response
    {
        // Validate the receipt against Apple's servers
        $appStoreUrl = 'https://buy.itunes.apple.com/verifyReceipt';
        $appStoreSandboxUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
        $sharedSecret = config('pay.apple.app_shared_secret');

        $response = Http::post($appStoreUrl, [
            'receipt-data' => $receiptData,
            'password' => $sharedSecret,
            'exclude-old-transactions' => $oldTransaction,
        ]);

        if ($response->json('status') == 21007) {
            // Use sandbox URL for sandbox receipts
            $response = Http::post($appStoreSandboxUrl, [
                'receipt-data' => $receiptData,
                'password' => $sharedSecret,
                'exclude-old-transactions' => $oldTransaction,
            ]);
        }

        return $response;
    }

    // Function to determine payment status
    public static function determinePaymentStatus($expiresDate, $isTrialPeriod, $isInBillingRetryPeriod, $expirationIntent): string
    {
        $currentTime = now()->unix(); // Get current time in milliseconds

        // 1. Check if the subscription is still valid (not expired)
        if ($expiresDate && $currentTime < $expiresDate) {
            // 2. If the user is in trial period, they haven't actually paid yet
            if ($isTrialPeriod == 'true') {
                return 'unpaid';
            }

            // 3. If the subscription is in billing retry period, payment failed
            if ($isInBillingRetryPeriod == 'true' || $expirationIntent == '2') {
                return 'payment_failed';
            }

            // 4. Otherwise, subscription is active and payment is completed
            return 'paid';
        }

        // 5. If the subscription is expired, consider it unpaid or expired
        return 'unpaid';
    }

    // Function to determine subscription status based on Apple's fields
    public static function determineSubscriptionStatus($expiresDate, $cancellationDate, $isTrialPeriod, $isInBillingRetryPeriod, $pendingRenewal): string
    {
        $currentTime = now()->unix();

        // 1. Check if the subscription is in trial period
        if ($isTrialPeriod == 'true' && $currentTime < $expiresDate) {
            return 'trial';
        }

        // 2. Check if the subscription is revoked (canceled)
        if ($cancellationDate) {
            return 'revoked';
        }

        // 3. Check if the subscription is active
        if ($expiresDate && $currentTime < $expiresDate) {
            // If the subscription is in billing retry period, consider it as 'failed_to_renew'
            if ($isInBillingRetryPeriod == 'true') {
                return 'failed_to_renew';
            }

            return 'active';
        }

        // 4. Check if the subscription failed to renew
        if ($pendingRenewal && isset($pendingRenewal['expiration_intent'])) {
            $expirationIntent = $pendingRenewal['expiration_intent'];
            if ($expirationIntent == '1') {
                return 'canceled'; // User canceled the subscription
            }
            if ($expirationIntent == '2') {
                return 'failed_to_renew'; // Billing issue caused renewal failure
            }
        }

        // 5. If the subscription is expired
        if ($expiresDate && $currentTime >= $expiresDate) {
            return 'expired';
        }

        return 'expired'; // Default to expired if no other condition matches
    }

    // Function to determine membership status
    public static function determineMembershipStatus($expiresDate, $cancellationDate, $isTrialPeriod): string
    {
        $currentTime = now()->unix();           // Get current time in milliseconds

        // 1. Check for trial status
        if ($isTrialPeriod == 'true' && $currentTime < $expiresDate) {
            return 'trial'; // 用户当前处于试用期内
        }

        // 2. Check for active status
        if ($expiresDate && $currentTime < $expiresDate && !$cancellationDate) {
            return 'active'; // 用户当前订阅有效
        }

        // 3. If the subscription has expired or canceled
        return 'expired';                       // 用户的订阅已过期
    }

    public static function getExpirationReason($expirationIntent): string
    {
        return match ($expirationIntent) {
            1 => '用户取消了自动续订',
            2 => '系统尝试扣款失败',
            3 => '用户没有同意提价续订',
            4 => '用户的订阅已经被退款',
            5 => '用户在不同的国家或地区切换了订阅',
            default => '',
        };
    }

    public static function getSubscriptionSuccessCount($latestReceiptInfo, $transactionId): int
    {
        $successCount = 0;

        // 遍历所有交易记录
        foreach ($latestReceiptInfo as $transaction) {
            // 检查是否是成功地订阅交易类型
            if ($transaction['original_transaction_id'] == $transactionId && $transaction['is_trial_period'] == 'false') {
                $successCount++;
            }
        }

        return $successCount;
    }

    public static function getSubscriptionFailureCount($pendingRenewalInfo, $transactionId): int
    {
        $failureCount = 0;

        foreach ($pendingRenewalInfo as $renewalInfo) {
            // 如果用户的订阅处于自动续订失败后的宽限期
            if ($renewalInfo['original_transaction_id'] == $transactionId && !empty($renewalInfo['is_in_billing_retry_period'])) {
                $failureCount++;
            }
        }

        return $failureCount;
    }

    public static function calculateTotalAmountPaid($latestReceiptInfo, $transactionId)
    {
        $totalAmount = 0;

        // 遍历所有交易记录
        foreach ($latestReceiptInfo as $transaction) {
            if ($transaction['original_transaction_id'] == $transactionId && $transaction['is_trial_period'] == 'false') {
                // 根据 product_id 从系统中获取价格（以美元为例）
                $productId = $transaction['product_id'];
                $purchaseAmount = self::getProductPriceById($productId); // 获取产品价格

                $totalAmount += $purchaseAmount;
            }
        }

        return $totalAmount;
    }

    // 假设你在数据库中有一个表格存储 product_id 和价格信息
    public static function getProductPriceById($productId)
    {
        $cacheKey = "productPriceCacheByPayProductId:$productId";
        $price = cache($cacheKey);
        if ($price) {
            return $price;
        }

        // 查询数据库，获取该 product_id 的价格
        $price = MemberProduct::query()->where('pay_product_id', $productId)->value('price');
        cache()->put($cacheKey, $price, 60);

        return $price;
    }


}
