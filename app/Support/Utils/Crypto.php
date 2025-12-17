<?php

namespace App\Support\Utils;


namespace App\Utils;

use Exception;

class Crypto
{
    protected static $key;
    protected static $iv;
    protected static $signKey;

    public static function init()
    {
        self::$key = base64_decode(config('crypto.key'));
        self::$iv = base64_decode(config('crypto.iv'));
        self::$signKey = config('crypto.sign_key');
    }

    // 加密数据
    public static function encrypt(array $data): string
    {
        self::init();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $encrypted = openssl_encrypt($json, 'AES-256-CBC', self::$key, OPENSSL_RAW_DATA, self::$iv);
        return base64_encode($encrypted);
    }

    // 解密数据
    public static function decrypt(string $encryptedBase64): array
    {
        self::init();
        $encrypted = base64_decode($encryptedBase64);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', self::$key, OPENSSL_RAW_DATA, self::$iv);
        if ($decrypted === false) {
            throw new Exception('解密失败');
        }
        return json_decode($decrypted, true);
    }

    // 生成签名
    public static function sign(string $data): string
    {
        self::init();
        return hash_hmac('sha256', $data, self::$signKey);
    }

    // 验证签名
    public static function verify(string $data, string $sign): bool
    {
        return hash_equals(self::sign($data), $sign);
    }

    /**
     * @param mixed $iv
     */
    public static function isOpen(): bool
    {
        return config("crypto.status");
    }
}

