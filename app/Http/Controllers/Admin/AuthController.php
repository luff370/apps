<?php

namespace App\Http\Controllers\Admin;

use App\Support\Utils\Captcha;
use App\Support\Services\CacheService;
use App\Services\System\Admin\SystemAdminServices;

/**
 * 后台登陆
 * Class Login
 *
 * @package App\Http\Controllers\Admin
 */
class AuthController extends Controller
{
    /**
     * Login constructor.
     *
     * @param SystemAdminServices $service
     */
    public function __construct(SystemAdminServices $service)
    {
        $this->service = $service;
    }

    /**
     * 验证码
     */
    public function captcha()
    {
        return app(Captcha::class)->create();
    }

    /**
     */
    public function ajcaptcha()
    {
        $captchaType = request()->get('captchaType');

        return $this->success(aj_captcha_create($captchaType));
    }

    /**
     * 一次验证
     */
    public function ajcheck()
    {
        [$token, $pointJson, $captchaType] = $this->getMore([
            ['token', ''],
            ['pointJson', ''],
            ['captchaType', ''],
        ], true);
        try {
            aj_captcha_check_one($captchaType, $token, $pointJson);

            return $this->success();
        } catch (\Throwable $e) {
            return $this->fail(400336);
        }
    }

    /**
     * 登陆
     */
    public function login()
    {
        [$account, $password, $key, $captchaVerification, $captchaType] = $this->getMore([
            'account',
            'pwd',
            'key',
            'captchaVerification',
            'captchaType',
        ], true);

        if ($captchaVerification != '') {
            try {
                aj_captcha_check_two($captchaType, $captchaVerification);
            } catch (\Throwable $e) {
                return $this->fail(400336);
            }
        }

        $this->validateWithScene(['account' => $account, 'pwd' => $password], \App\Http\Requests\Setting\SystemAdminValidata::class, 'get');
        $result = $this->service->login($account, $password, 'admin', $key);
        if (!$result) {
            $num = CacheService::redisHandler()->get('login_captcha', 1);
            if ($num > 1) {
                return $this->fail(400140, ['login_captcha' => 1]);
            }
            CacheService::redisHandler()->set('login_captcha', $num + 1, 60);

            return $this->fail(400140, ['login_captcha' => 0]);
        }
        CacheService::redisHandler()->delete('login_captcha');

        return $this->success($result);
    }

    /**
     * 获取后台登录页轮播图以及LOGO
     */
    public function info()
    {
        return $this->success($this->service->getLoginInfo());
    }

    /**
     * 退出登陆
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout()
    {
        $token = trim(ltrim(request()->header(config('cookie.token_name', 'Authori-zation')), 'Bearer'));
        CacheService::redisHandler('admin')->delete(md5($token));

        return $this->success();
    }
}
