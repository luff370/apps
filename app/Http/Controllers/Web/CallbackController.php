<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Support\Services\AlipayService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Alipay\AuthService;

class CallbackController extends Controller
{
    public function alipayAuth(Request $request, AuthService $alipayAuthService)
    {
        logger()->info("alipayAuth data", $request->all());

        $authCode = $request->get('auth_code');
        $state = $request->get('state');

        if (!$authCode) {
            return view('alipay.auth_fail', ['msg' => '缺少授权码']);
        }

        try {
            $alipayService = new AlipayService(config('alipay'));

            $tokenData = $alipayService->systemOauthToken($request->get("auth_code"));

            $userInfo = $alipayService->getUserInfo($tokenData['access_token']);
            logger()->info("alipayAuth userInfo", $userInfo);
            // 1. 获取 access_token
            // $tokenData = $alipayAuthService->getAccessToken($authCode);
            // logger()->info("alipayAuth tokenData", $tokenData);

            // 绑定支付宝user_id到你自己系统的用户
            // User::query()->where('id', authUserId())->update(['alipay_user_id' => $userInfo['user_id']]);

            return view('alipay.auth_success', [
                'nick' => $userInfo['nick_name'] ?? '支付宝用户',
                'avatar' => $userInfo['avatar'] ?? null,
            ]);
        } catch (\Exception $e) {
            logger()->error("alipayAuth error:" . $e->getMessage());

            return view('alipay.auth_fail', ['msg' => '授权失败']);
        }

    }
}
