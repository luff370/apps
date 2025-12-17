<?php

namespace App\Services;

use App\Models\User;
use App\Support\Utils\Token;
use App\Models\ThirdLoginUser;
use App\Exceptions\AuthException;
use Illuminate\Support\Facades\DB;

class AuthService extends Service
{
    public function getUserIdByUuid($uuid)
    {
        $cacheKey = "userId_by_uuid:$uuid";

        $userId = cache($cacheKey);
        if (empty($userId)) {
            $userId = User::query()->where('uuid', $uuid)->pluck('id')->first();
            if (!empty($userId)) {
                cache([$cacheKey => $userId], now()->addDays(90));
            }
        }

        return $userId;
    }

    /**
     * @throws \App\Exceptions\AuthException
     */
    public function thirdLogin($type, $uuid, $platform, $appId, $thirdInfo): string
    {
        // 判断此用户是否已注册，已注册则直接进行登录操作
        $thirdUser = ThirdLoginUser::query()->with(['user'])
            ->where('type', $type)
            ->where('third_user_id', $thirdInfo['third_user_id'])
            ->first();
        if ($thirdUser) {
            // 生成token
            return Token::generate(['user_id' => $thirdUser['user']['id'], 'login_type' => $type, 'is_reg' => 1]);
        }

        try {
            DB::beginTransaction();
            // 默认头像
            $thirdInfo['avatar'] = !empty($thirdInfo['avatar']) ? $thirdInfo['avatar'] : 'http://novel-store.qaqzz.com/default_boy.png';
            // 未注册，通过uuid获取用户信息进行绑定操作
            $userInfo = User::query()->where('app_id', $appId)->where('uuid', $uuid)->first();
            if (empty($userInfo)) {
                $userRegData = [
                    'uuid' => $uuid,
                    'app_id' => $appId,
                    'platform' => $this->getPlatform(),
                    'os_version' => $this->getOsVersion(),
                    'package_name' => $this->getAppPackageName(),
                    'market_channel'=>$this->getMarketChannel(),
                    'device_sn'=>$this->getDevice(),
                    'nickname' => $thirdInfo['nickname'],
                    'avatar' => $thirdInfo['avatar'],
                    'source' => $platform,
                    'reg_way' => $type,
                    'is_reg' => 1,
                    'reg_ip' => request()->getClientIp(),
                    'region' => ip2region(request()->getClientIp()),
                    'reg_time' => time(),
                    'update_time' => time(),
                ];
                $userInfo = User::query()->create($userRegData);
            }

            // 第三方用户信息保存
            $thirdInfo['type'] = $type;
            $thirdInfo['user_id'] = $userInfo['id'];
            ThirdLoginUser::query()->create($thirdInfo);

            $userInfo["has_{$type}_user"] = 1;
            $userInfo['login_way'] = $type;
            if ($userInfo['is_reg'] == 0) {
                $userInfo['is_reg'] = 1;
                $userInfo['nickname'] = $thirdInfo['nickname'];
                $userInfo['avatar'] = $thirdInfo['avatar'];
            }
            $userInfo->save();
            DB::commit();

            return Token::generate(['user_id' => $userInfo['id'], 'login_type' => $type, 'is_reg' => 1]);
        } catch (\Exception $exception) {
            DB::rollBack();
            logger()->error($exception->getMessage());
            throw new AuthException('登录失败，请重试');
        }
    }
}
