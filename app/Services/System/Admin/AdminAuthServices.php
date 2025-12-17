<?php

namespace App\Services\System\Admin;

use App\Services\Service;
use App\Support\Utils\JwtAuth;
use App\Exceptions\AuthException;
use Firebase\JWT\ExpiredException;
use App\Support\Services\CacheService;
use App\Dao\System\Admin\SystemAdminDao;

/**
 * admin授权service
 * Class AdminAuthServices
 *
 * @package App\Services\System\admin
 */
class AdminAuthServices extends Service
{
    /**
     * 构造方法
     * AdminAuthServices constructor.
     *
     * @param SystemAdminDao $dao
     */
    public function __construct(SystemAdminDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取Admin授权信息
     *
     * @param string $token
     * @param int $code
     *
     * @return array
     * @throws \App\Exceptions\AuthException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function parseToken(string $token, int $code = 110003): array
    {
        if (!$token || $token === 'undefined') {
            throw new AuthException($code);
        }

        /** @var JwtAuth $jwtAuth */
        $jwtAuth = app(JwtAuth::class);
        //设置解析token
        try {
            [$id, $type, $pwd] = $jwtAuth->parseToken($token);
        } catch (\Exception $exception) {
            throw new AuthException($code);
        }

        //检测token是否过期
        $md5Token = md5($token);
        if (!$cacheToken = CacheService::getTokenBucket($md5Token)) {
            $this->authFailAfter($id, $type);
            throw new AuthException($code);
        }

        //是否超出有效次数
        if (isset($cacheToken['invalidNum']) && $cacheToken['invalidNum'] >= 3) {
            $this->authFailAfter($id, $type);
            throw new AuthException($code);
        }

        //验证token
        try {
            $jwtAuth->verifyToken($token);
        } catch (ExpiredException $e) {
            $cacheToken['invalidNum'] = isset($cacheToken['invalidNum']) ? $cacheToken['invalidNum']++ : 1;
            CacheService::setTokenBucket($md5Token, $cacheToken, $cacheToken['exp']);
        } catch (\Throwable $e) {
            $this->authFailAfter($id, $type);
            throw new AuthException($code);
        }

        //获取管理员信息
        $adminInfo = $this->dao->getRowByCache($id);
        if (!$adminInfo || !$adminInfo['id']) {
            $this->authFailAfter($id, $type);
            throw new AuthException($code);
        }

        if ($pwd !== md5($adminInfo['pwd'])) {
            throw new AuthException($code);
        }

        $adminInfo['type'] = $type;

        return $adminInfo;
    }

    /**
     * token验证失败后事件
     */
    protected function authFailAfter($id, $type)
    {
        return;
    }
}
