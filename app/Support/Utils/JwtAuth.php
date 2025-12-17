<?php

namespace App\Support\Utils;

use Firebase\JWT\Key;
use Firebase\JWT\JWT;
use App\Exceptions\AdminException;
use App\Support\Services\CacheService;

/**
 * Jwt
 * Class JwtAuth
 *
 * @package App\Support\Utils
 */
class JwtAuth
{
    /**
     * alg 默认加密方式
     *
     * @var string
     */
    public static $alg = 'HS256';

    /**
     * 获取token
     *
     * @param int|string $id
     * @param string $type
     * @param array $params
     *
     * @return array
     */
    public static function getToken($id, string $type, array $params = []): array
    {
        $host = config('app.url');
        $time = time();
        $exp_time = strtotime('+ 30day');
        $params += [
            'iss' => $host,
            'aud' => $host,
            'iat' => $time,
            'nbf' => $time,
            'exp' => $exp_time,
        ];
        $params['jti'] = compact('id', 'type');
        $token = JWT::encode($params, config('app.key'), self::$alg);

        return compact('token', 'params');
    }

    /**
     * 解析token
     *
     * @param string $token
     *
     * @return array
     */
    public static function parseToken(string $token): array
    {
        $payload = JWT::decode($token, new Key(config('app.key'), self::$alg));

        return [$payload->jti->id, $payload->jti->type, $payload->pwd ?? ''];
    }

    /**
     * 验证token
     */
    public function verifyToken(string $token)
    {
        JWT::$leeway = 60;

        JWT::decode($token, new Key(config('app.key'), self::$alg));
    }

    /**
     * 获取token并放入令牌桶
     *
     * @param $id
     * @param string $type
     * @param array $params
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function createToken($id, string $type, array $params = []): array
    {
        $tokenInfo = self::getToken($id, $type, $params);
        $exp = $tokenInfo['params']['exp'] - $tokenInfo['params']['iat'] + 60;
        $res = CacheService::setTokenBucket(md5($tokenInfo['token']), ['uid' => $id, 'type' => $type, 'token' => $tokenInfo['token'], 'exp' => $exp], (int) $exp, $type);
        if (!$res) {
            throw new AdminException(100023);
        }

        return $tokenInfo;
    }
}
