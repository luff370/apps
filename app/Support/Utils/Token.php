<?php

namespace App\Support\Utils;

use App\Models\User;
use App\Exceptions\AuthException;

class Token
{
    // token缓存时长（30天）
    const ttl = 3600 * 24 * 30;

    // token续期剩余时间
    const renewal = 3600 * 24 * 10;

    private static function cacheKey(string $token): string
    {
        return 'api-token:' . $token;
    }

    public static function generate(array $params, $token = ''): string
    {
        $params['ita'] = time();
        $params['exp'] = time() + self::ttl;

        $params = json_encode($params);
        if (empty($token)) {
            $token = md5($params);
        }
        cache()->put(self::cacheKey($token), $params, self::ttl);

        return $token;
    }

    /**
     * @throws \App\Exceptions\AuthException
     */
    public static function verify($token)
    {
        $jsonData = cache(self::cacheKey($token));
        if (empty($jsonData)) {
            $uuid = request()->header('Uuid');
            if (!empty($uuid)) {
                $user = User::query()->select(['id', 'is_reg'])->where('uuid', $uuid)->first();
                if (!empty($user)) {
                    self::generate(['user_id' => $user['id'], 'login_way' => 'account', 'is_reg' => $user['is_reg']], $token);
                    $jsonData = cache(self::cacheKey($token));
                }
            }
        }

        if (empty($jsonData)) {
            throw new AuthException('登录失效，请重新登录');
        }

        $data = json_decode($jsonData, true);
        if (empty($data) || empty($data['user_id'])) {
            throw new AuthException('登录失效，Token解析失败');
        }

        // token续期
        if (!empty($data['exp']) && $data['exp'] - time() < self::renewal) {
            $data['exp'] = time() + self::ttl;
            cache()->put(self::cacheKey($token), json_encode($data), self::ttl);
        }

        return $data;
    }

    /**
     * 用户注销
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function signOut(): bool
    {
        $token = request()->header('Token');
        if ($token) {
            cache()->delete(self::cacheKey($token));
        }

        return true;
    }
}
