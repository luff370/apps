<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserAuthDao;
use App\Support\Utils\JwtAuth;
use App\Exceptions\AuthException;
use App\Support\Services\CacheService;

/**
 *
 * Class UserAuthServices
 *
 * @package App\Services\User
 */
class UserAuthServices extends Service
{
    /**
     * UserAuthServices constructor.
     *
     * @param UserAuthDao $dao
     */
    public function __construct(UserAuthDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取授权信息
     *
     * @param $token
     *
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException\
     */
    public function parseToken($token): array
    {
        $md5Token = is_null($token) ? '' : md5($token);

        if ($token === 'undefined') {
            throw new AuthException(110002);
        }
        if (!$token || !$tokenData = CacheService::getTokenBucket($md5Token)) {
            throw new AuthException(110002);
        }

        if (!is_array($tokenData) || empty($tokenData) || !isset($tokenData['uid'])) {
            throw new AuthException(110002);
        }

        /** @var JwtAuth $jwtAuth */
        $jwtAuth = app(JwtAuth::class);
        //设置解析token
        [$id, $type] = $jwtAuth->parseToken($token);

        try {
            $jwtAuth->verifyToken();
        } catch (\Throwable $e) {
            if (!request()->isCli()) {
                CacheService::clearToken($md5Token);
            }
            throw new AuthException(110003);
        }

        $user = $this->dao->get(['uid' => $id, 'is_del' => 0]);

        if (!$user || $user->uid != $tokenData['uid']) {
            if (!request()->isCli()) {
                CacheService::clearToken($md5Token);
            }
            throw new AuthException(110004);
        }
        $tokenData['type'] = $type;

        return compact('user', 'tokenData');
    }
}
