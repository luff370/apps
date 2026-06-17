<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Support\Utils\Token;
use App\Models\UserFeedback;
use Illuminate\Support\Carbon;
use App\Services\User\UserServices;
use App\Models\TrafficViolationContent;

class UserController extends Controller
{
    public function __construct(UserServices $service)
    {
        $this->service = $service;
    }

    /**
     * 用户信息详情
     */
    public function info()
    {
        $userId = authUserId();
        $userInfo = User::query()->findOrFail($userId);

        $data = [
            'id' => $userInfo['id'],
            'avatar' => $userInfo['avatar'],
            'nickname' => $userInfo['nickname'],
            'email' => $userInfo['email'],
            'is_reg' => $userInfo['is_reg'],
            'gender' => $userInfo['gender'],
            'balance' => $userInfo['balance'],
            'integral' => $userInfo['integral'],
            'login_way' => $userInfo['login_way'],
            'is_vip' => $userInfo['is_vip'],
            'vip_type' => $userInfo['vip_type'],
            'alipay_user_id' => $userInfo['alipay_user_id'],
            'overdue_time' => $userInfo['is_vip'] ? Carbon::parse($userInfo['overdue_time'])->toDateString() : null,
        ];

        switch ($this->getAppId()) {
            case 10001: // chatAi
                break;
            case 10002: // 违章随手拍
            case 10037: // 随手拍3
            case 10038: // 随手拍3
                $data['news'] = TrafficViolationContent::query()->where('user_id', $userId)->where('notification_status', 1)->count();
                break;
        }

        return $this->success($data);
    }

    /**
     * 设备信息更新
     */
    public function deviceInfoUpdate(Request $request)
    {
         $this->service->update(authUserId(), [
            'device_token' => $request->get('device_token'),
            'platform' => $this->getPlatform(),
            'last_ip' => request()->getClientIp(),
            'os_version' => $this->getOsVersion(),
            'app_version' => $this->getAppVersion(),
        ]);

         return $this->success();
    }

    /**
     * 阅读历史记录
     */
    public function readingHistory(UserBookService $service)
    {
        $data = $service->readRecordList(authUserId());

        return $this->success($data);
    }

    /**
     * 意见反馈
     */
    public function feedback(Request $request)
    {
        $feedback = $request->all();
        $feedback['user_id'] = authUserId();
        $feedback['app_id'] = $this->getAppId();
        $feedback['version'] = $this->getAppVersion();
        $feedback['market_channel'] = $this->getMarketChannel();

        UserFeedback::query()->create($feedback);

        return $this->success();
    }

    /**
     * 意见反馈记录
     */
    public function feedbackList()
    {
        $list = UserFeedback::query()
            ->where('user_id', authUserId())
            ->where('app_id', $this->getAppId())
            ->select([
                'id',
                'type',
                'content',
                'images',
                'email',
                'phone',
                'status',
                'recover_content',
                'admin_name',
                'create_time',
                'update_time',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (UserFeedback $feedback) {
                $createTime = (int) $feedback->getRawOriginal('create_time');
                $updateTime = (int) $feedback->getRawOriginal('update_time');

                return [
                    'id' => $feedback->id,
                    'type' => $feedback->type,
                    'content' => $feedback->content,
                    'images' => $feedback->images ?: [],
                    'email' => $feedback->email,
                    'phone' => $feedback->phone,
                    'status' => $feedback->status,
                    'recover_content' => $feedback->recover_content,
                    'admin_name' => $feedback->admin_name,
                    'create_time' => $createTime,
                    'create_time_text' => $createTime ? Carbon::createFromTimestamp($createTime)->toDateTimeString() : '',
                    'update_time' => $updateTime,
                    'update_time_text' => $updateTime ? Carbon::createFromTimestamp($updateTime)->toDateTimeString() : '',
                ];
            });

        return $this->success($list);
    }

    /**
     * 用户退出登录
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function logout()
    {
        Token::signOut();

        return $this->success();
    }

    /**
     * 用户注销
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function singOut()
    {
        $userId = authUserId();

        User::query()->where('id', $userId)->update(['is_del' => 1]);
        Token::signOut();

        return $this->success();
    }
}
