<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Facebook\Facebook;
use Illuminate\Http\Request;
use App\Support\Utils\Token;
use App\Support\Utils\Apple;
use App\Services\AuthService;
use App\Support\Services\AlipayService;
use App\Http\Requests\Auth\AccountRegRequest;

class AuthController extends Controller
{
    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    public function regByAccount(AccountRegRequest $request): \Illuminate\Http\JsonResponse
    {
        // 限制单设备注册用户数量
        // $userCount = User::query()->where('uuid', $this->getUuid())->where('app_id', $this->getAppId())->count();
        // if ($userCount >= 6) {
        //     return $this->fail('该设备已注册，请直接登录');
        // }

        $count = User::query()->where('app_id', $this->getAppId())->where('account', $request->get('account'))->count();
        if ($count > 0) {
            return $this->fail('该账号已存在');
        }

        $userRegData = [
            'uuid' => $this->getUuid(),
            'app_id' => $this->getAppId(),
            'nickname' => getRandomStr(8, false),
            'platform' => $this->getPlatform(),
            'os_version' => $this->getOsVersion(),
            'package_name' => $this->getAppPackageName(),
            'market_channel' => $this->getMarketChannel(),
            'device_sn' => $this->getDevice(),
            'login_way' => 'account',
            'is_reg' => 1,
            'reg_time' => time(),
            'update_time' => time(),
            'reg_ip' => $request->getClientIp(),
            'region' => ip2region($request->getClientIp()),
            'account' => $request->get('account'),
            'password' => md5($request->get('password')),
        ];

        $user = User::query()->create($userRegData);

        // 生成token
        $token = Token::generate(['user_id' => $user['id'], 'login_way' => 'account', 'is_reg' => $user['is_reg']]);

        return $this->success(['token' => $token, 'login_way' => 'account', 'is_reg' => $user['is_reg']]);
    }

    public function loginByAccount(Request $request)
    {
        $account = $request->get('account');
        $user = User::query()->where('app_id', $this->getAppId())->where('account', $account)->first();
        if (empty($user)) {
            return $this->fail('账号不存在');
        }

        if ($user['is_del'] == 1) {
            return $this->fail('该账号已注销，如需使用请联系客服');
        }

        if (md5($request->get('password')) != $user['password']) {
            return $this->fail('密码错误，请确认');
        }

        // 生成token
        $token = Token::generate(['user_id' => $user['id'], 'login_way' => 'account', 'is_reg' => $user['is_reg']]);

        return $this->success(['token' => $token, 'login_way' => 'account', 'is_reg' => $user['is_reg']]);
    }

    public function loginByUuid(Request $request)
    {
        $uuid = $this->getUuid();

        if (empty($uuid)) {
            return $this->fail('uuid 不能为空');
        }

        $user = User::query()->where('app_id', $this->getAppId())->where('uuid', $uuid)->first();
        if (empty($user)) {
            $userRegData = [
                'uuid' => $uuid,
                'app_id' => $this->getAppId(),
                'platform' => $this->getPlatform(),
                'os_version' => $this->getOsVersion(),
                'package_name' => $this->getAppPackageName(),
                'market_channel' => $this->getMarketChannel(),
                'device_sn' => $this->getDevice(),
                'nickname' => getRandomStr(8, false),
                'avatar' => '',
                'login_way' => 'uuid',
                'is_reg' => 0,
                'reg_ip' => $request->getClientIp(),
                'region' => ip2region($request->getClientIp()),
                'reg_time' => time(),
                'update_time' => time(),
            ];
            $user = User::query()->create($userRegData);
        }

        // 生成token
        $token = Token::generate(['user_id' => $user['id'], 'login_way' => 'uuid', 'is_reg' => $user['is_reg']]);

        return $this->success(['token' => $token, 'login_way' => 'uuid', 'is_reg' => $user['is_reg']]);
    }

    /**
     * facebook登录
     *
     * @throws \Facebook\Exceptions\FacebookSDKException
     * @throws \App\Exceptions\AuthException
     */
    public function loginByFacebook(Request $request)
    {
        $uuid = $request->header('Uuid');
        $platform = $request->header('Platform');
        $appId = $request->header('App-Id');

        $access_token = $request->get('access_token');
        if (!$access_token) {
            return $this->failed('access_token value cannot be empty ');
        }
        logger()->info('facebook-login-token', [$access_token]);

        $fb = new Facebook([
            'app_id' => config('facebook.appId'),
            'app_secret' => config('facebook.secret'),
            'default_graph_version' => 'v2.10',
        ]);

        $avatar_url = '';
        try {
            $response = $fb->get(
                '/me',
                $access_token
            );
            $facebook_user = $response->getDecodedBody();
            if (!isset($facebook_user['id'])) {
                return $this->failed('Facebook信息获取失败');
            }
            //获取用户头像
            // $response = $fb->get(
            //     "/{$facebook_user['id']}/picture?type=large&redirect=false",
            //     $access_token
            // );
            // $facebook_user_avatar = $response->getDecodedBody();
            // if (isset($facebook_user_avatar['data']['url'])) {
            //     $avatar_url = $facebook_user_avatar['data']['url']; // 获取头像
            // }
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage(), [$exception->getFile(), $exception->getLine()]);

            return $this->failed($exception->getMessage());
        }

        $thirdUserInfo = [
            'third_user_id' => $facebook_user['id'],
            'nickname' => $facebook_user['name'],
            'avatar' => $avatar_url,
        ];
        $token = $this->service->thirdLogin('facebook', $uuid, $platform, $appId, $thirdUserInfo);

        return $this->success(['token' => $token, 'login_way' => 'facebook', 'is_reg' => 1]);
    }

    /**
     * google 登录
     *
     * @throws \App\Exceptions\AuthException
     */
    public function loginByGoogle(Request $request)
    {
        $params = $request->all();
        $uuid = $request->header('Uuid');
        $platform = $request->header('Platform');
        $appId = $request->header('App-Id');

        if (empty($params['id'])) {
            return $this->failed('google 登录失败，用户ID不能为空');
        }

        $thirdUserInfo = [
            'third_user_id' => $params['id'],
            'nickname' => $params['displayName'] ?? '',
            'avatar' => $params['photoUrl'] ?? '',
            'email' => $params['email'] ?? '',
        ];
        $token = $this->service->thirdLogin('google', $uuid, $platform, $appId, $thirdUserInfo);

        return $this->success(['token' => $token, 'login_way' => 'google', 'is_reg' => 1]);
    }

    /**
     * apple 登录
     *
     * @throws \App\Exceptions\AuthException
     */
    public function loginByApple(Request $request)
    {
        $uuid = $request->header('Uuid');
        $platform = $request->header('Platform');
        $appId = $request->header('App-Id');
        $openid = $request->get('userID', '');
        $verifyToken = $request->get('verifyToken', '');
        $packageName = $this->getAppPackageName();

        // idToken 验证
        $payload = Apple::tokenValidate($verifyToken, $packageName);

        $thirdUserInfo = [
            'third_user_id' => $openid,
            'email' => $payload->getEmail(),
            'nickname' => '',
            'avatar' => '',
        ];

        $token = $this->service->thirdLogin('apple', $uuid, $platform, $appId, $thirdUserInfo);

        return $this->success(['token' => $token, 'login_way' => 'apple', 'is_reg' => 1]);
    }

    public function alipayAuth(Request $request)
    {
        $request->validate([
            'auth_code' => 'required|string',
        ]);

        $alipayService = new AlipayService(config('alipay'));
        try {
            // 1. 获取 access_token
            $tokenData = $alipayService->systemOauthToken($request->get("auth_code"));

            // 2. 获取用户信息
            $userInfo = $alipayService->getUserInfo($tokenData['access_token']);
            logger()->info('alipay auth userinfo', $userInfo);

            if (empty($userInfo['user_id'])) {
                return $this->fail('无用户ID授权');
            }

            // 绑定支付宝user_id到你自己系统的用户
            User::query()->where('id', authUserId())->update(['alipay_user_id' => $userInfo['user_id']]);

            return $this->success('授权成功');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
