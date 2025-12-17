<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Dao\User\UserDao;
use App\Services\Service;
use App\Support\Services\CacheService;
use Illuminate\Support\Facades\Config;
use App\Services\Wechat\WechatUserServices;
use App\Services\message\notice\SmsServices;
use App\Services\yihaotong\SmsRecordServices;

/**
 *
 * Class LoginServices
 *
 * @package App\Services\User
 */
class LoginServices extends Service
{
    /**
     * LoginServices constructor.
     *
     * @param UserDao $dao
     */
    public function __construct(UserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * H5账号登陆
     *
     * @param $account
     * @param $password
     * @param $spread
     *
     * @return array
     */
    public function login($account, $password, $spread)
    {
        $user = $this->dao->getOne(['account|phone' => $account, 'is_del' => 0]);
        if ($user) {
            if ($user->pwd !== md5((string) $password)) {
                throw new AdminException(410025);
            }
            if ($user->pwd === md5('123456')) {
                throw new AdminException(410026);
            }
        } else {
            throw new AdminException(410025);
        }
        if (!$user['status']) {
            throw new AdminException(410027);
        }

        //更新用户信息
        $this->updateUserInfo(['code' => $spread], $user);
        $token = $this->createToken((int) $user['uid'], 'api');
        if ($token) {
            return ['token' => $token['token'], 'expires_time' => $token['params']['exp']];
        } else {
            throw new AdminException(410019);
        }
    }

    /**
     * 更新用户信息
     *
     * @param $user
     * @param $userInfo
     * @param false $is_new
     *
     * @return bool
     */
    public function updateUserInfo($user, $userInfo, $is_new = false)
    {
        $data = [];
        $data['nickname'] = !isset($user['nickname']) || !$user['nickname'] ? $userInfo->nickname : $user['nickname'];
        $data['avatar'] = !isset($user['headimgurl']) || !$user['headimgurl'] ? $userInfo->avatar : $user['headimgurl'];
        $data['phone'] = !isset($user['phone']) || !$user['phone'] ? $userInfo->phone : $user['phone'];
        $data['last_time'] = time();
        $data['last_ip'] = app()->request->ip();
        $spreadUid = $user['code'] ?? 0;
        //如果扫了员工邀请码，上级，代理商，区域代理都会改动。
        if (isset($user['is_staff']) && !$userInfo['is_agent'] && !$userInfo['is_division']) {
            $spreadInfo = $this->dao->get($spreadUid);
            if ($userInfo['uid'] != $spreadUid) {
                $data['spread_uid'] = $spreadUid;
                $data['spread_time'] = $userInfo->last_time;
            }
            $data['agent_id'] = $spreadInfo->agent_id;
            $data['division_id'] = $spreadInfo->division_id;
            $data['staff_id'] = $userInfo['uid'];
            $data['is_staff'] = $user['is_staff'] ?? 0;
            $data['division_type'] = 3;
            $data['division_change_time'] = time();
            $data['division_end_time'] = $spreadInfo->division_end_time;
            //如果店员切换代理商，则店员在之前代理商下推广的用户，他们的直接上级从当前店员变为之前代理商
            if ($userInfo->agent_id != 0 && $userInfo->agent_id != $spreadInfo->agent_id) {
                $this->dao->update($userInfo['uid'], ['spread_uid' => $userInfo->agent_id], 'spread_uid');
            }
        }
        if ($is_new) {
            if ($spreadUid) {
                $spreadInfo = $this->dao->get($spreadUid);
                $spreadUid = (int) $spreadUid;
                $data['spread_uid'] = $spreadUid;
                $data['spread_time'] = time();
                $data['agent_id'] = $spreadInfo->agent_id;
                $data['division_id'] = $spreadInfo->division_id;
                $data['staff_id'] = $spreadInfo->staff_id;
                //绑定用户后置事件
                event('user.register', [$spreadUid, $userInfo['user_type'], $userInfo['nickname'], $userInfo['uid'], 0]);
                //推送消息
                event('notice.notice', [['spreadUid' => $spreadUid, 'user_type' => $userInfo['user_type'], 'nickname' => $userInfo['nickname']], 'bind_spread_uid']);
            }
        } else {
            //永久绑定
            $store_brokerage_binding_status = sys_config('store_brokerage_binding_status', 1);
            if ($userInfo->spread_uid && $store_brokerage_binding_status == 1 && !isset($user['is_staff'])) {
                $data['login_type'] = $user['login_type'] ?? $userInfo->login_type;
            } else {
                //绑定分销关系 = 所有用户
                if (sys_config('brokerage_bindind', 1) == 1) {
                    //分销绑定类型为时间段且过期 ｜｜临时
                    $store_brokerage_binding_time = sys_config('store_brokerage_binding_time', 30);
                    if (!$userInfo['spread_uid'] || $store_brokerage_binding_status == 3 || ($store_brokerage_binding_status == 2 && ($userInfo['spread_time'] + $store_brokerage_binding_time * 24 * 3600) < time())) {
                        if ($spreadUid && $user['code'] != $userInfo->uid && $userInfo->uid != $this->dao->value(['uid' => $spreadUid], 'spread_uid')) {
                            $spreadInfo = $this->dao->get($spreadUid);
                            $spreadUid = (int) $spreadUid;
                            $data['spread_uid'] = $spreadUid;
                            $data['spread_time'] = time();
                            $data['agent_id'] = $spreadInfo->agent_id;
                            $data['division_id'] = $spreadInfo->division_id;
                            $data['staff_id'] = $spreadInfo->staff_id;
                            //绑定用户后置事件
                            event('user.register', [$spreadUid, $userInfo['user_type'], $userInfo['nickname'], $userInfo['uid'], 0]);
                            //推送消息
                            event('notice.notice', [['spreadUid' => $spreadUid, 'user_type' => $userInfo['user_type'], 'nickname' => $userInfo['nickname']], 'bind_spread_uid']);
                        }
                    }
                }
            }
        }
        if (!$this->dao->update($userInfo['uid'], $data, 'uid')) {
            throw new AdminException(100007);
        }

        return true;
    }

    public function verify(SmsServices $services, $phone, $type, $time, $ip)
    {
        if ($this->dao->getOne(['account' => $phone, 'is_del' => 0]) && $type == 'register') {
            throw new AdminException(410028);
        }
        $default = Config::get('sms.default', 'yihaotong');
        $defaultMaxPhoneCount = Config::get('sms.maxPhoneCount', 10);
        $defaultMaxIpCount = Config::get('sms.maxIpCount', 50);
        $maxPhoneCount = Config::get('sms.stores.' . $default . '.maxPhoneCount', $defaultMaxPhoneCount);
        $maxIpCount = Config::get('sms.stores.' . $default . '.maxIpCount', $defaultMaxIpCount);
        /** @var SmsRecordServices $smsRecord */
        $smsRecord = app(SmsRecordServices::class);
        if ($smsRecord->count(['phone' => $phone, 'add_ip' => $ip, 'time' => 'today']) >= $maxPhoneCount) {
            throw new AdminException(410029);
        }
        if ($smsRecord->count(['add_ip' => $ip, 'time' => 'today']) >= $maxIpCount) {
            throw new AdminException(410030);
        }
        $code = rand(100000, 999999);
        $data['code'] = $code;
        $data['time'] = $time;
        $res = $services->send(true, $phone, $data, 'verify_code');
        if ($res !== true) {
            throw new AdminException(410031);
        }

        return $code;
    }

    /**
     * H5用户注册
     *
     * @param $account
     * @param $password
     * @param $spread
     * @param string $user_type
     *
     * @return mixed
     */
    public function register($account, $password, $spread, $user_type = 'h5')
    {
        if ($this->dao->getOne(['account|phone' => $account, 'is_del' => 0])) {
            throw new AdminException(410028);
        }
        /** @var UserServices $userServices */
        $userServices = app(UserServices::class);
        $phone = $account;
        $data['account'] = $account;
        $data['pwd'] = md5((string) $password);
        $data['phone'] = $phone;
        if ($spread) {
            $data['spread_uid'] = $spread;
            $data['spread_time'] = time();
            $spreadInfo = $userServices->get($spread);
            $data['division_id'] = $spreadInfo['division_id'];
            $data['agent_id'] = $spreadInfo['agent_id'];
            $data['staff_id'] = $spreadInfo['staff_id'];
        }
        $data['real_name'] = '';
        $data['birthday'] = 0;
        $data['card_id'] = '';
        $data['mark'] = '';
        $data['addres'] = '';
        $data['user_type'] = $user_type;
        $data['add_time'] = time();
        $data['add_ip'] = app('request')->ip();
        $data['last_time'] = time();
        $data['last_ip'] = app('request')->ip();
        $data['nickname'] = substr(md5($account . time()), 0, 12);
        $data['avatar'] = sys_config('h5_avatar');
        $data['city'] = '';
        $data['language'] = '';
        $data['province'] = '';
        $data['country'] = '';
        $data['status'] = 1;
        if (!$re = $this->dao->save($data)) {
            throw new AdminException(410014);
        } else {
            $userServices->rewardNewUser((int) $re->uid);
            //用户生成后置事件
            event('user.register', [$spread, $user_type, $data['nickname'], $re->uid, 1]);
            //推送消息
            event('notice.notice', [['spreadUid' => $spread, 'user_type' => $user_type, 'nickname' => $data['nickname']], 'bind_spread_uid']);

            return $re;
        }
    }

    /**
     * 重置密码
     *
     * @param $account
     * @param $password
     *
     * @return bool
     */
    public function reset($account, $password)
    {
        $user = $this->dao->getOne(['account|phone' => $account, 'is_del' => 0]);
        if (!$user) {
            throw new AdminException(410032);
        }
        if (!$this->dao->update($user['uid'], ['pwd' => md5((string) $password)], 'uid')) {
            throw new AdminException(410033);
        }

        return true;
    }

    /**
     * 手机号登录
     *
     * @param $phone
     * @param $spread
     * @param string $user_type
     *
     * @return array
     */
    public function mobile($phone, $spread, string $user_type = 'h5')
    {
        //数据库查询
        $user = $this->dao->getOne(['account|phone' => $phone, 'is_del' => 0]);
        if (!$user) {
            $user = $this->register($phone, '123456', $spread, $user_type);
            if (!$user) {
                throw new AdminException(410034);
            }
        }

        if (!$user->status) {
            throw new AdminException(410027);
        }

        // 设置推广关系
        $this->updateUserInfo(['code' => $spread], $user);

        $token = $this->createToken((int) $user['uid'], 'api');
        if ($token) {
            return ['token' => $token['token'], 'expires_time' => $token['params']['exp']];
        } else {
            throw new AdminException(410019);
        }
    }

    /**
     * 切换登录
     *
     * @param $user
     * @param $from
     *
     * @return array
     */
    public function switchAccount($user, $from)
    {
        if ($from === 'h5') {
            $where = [['phone', '=', $user['phone']], ['user_type', '<>', 'h5'], ['is_del', '=', 0]];
            $login_type = 'wechat';
        } else {
            //数据库查询
            $where = [['account|phone', '=', $user['phone']], ['user_type', '=', 'h5'], ['is_del', '=', 0]];
            $login_type = 'h5';
        }
        $switch_user = $this->dao->getOne($where);
        if (!$switch_user) {
            return $this->fail(410035);
        }
        if (!$switch_user->status) {
            return $this->fail(410027);
        }
        $edit_data = ['login_type' => $login_type];
        if (!$this->dao->update($switch_user['uid'], $edit_data, 'uid')) {
            throw new AdminException(410036);
        }
        $token = $this->createToken((int) $switch_user['uid'], 'api');
        if ($token) {
            return ['token' => $token['token'], 'expires_time' => $token['params']['exp']];
        } else {
            throw new AdminException(410019);
        }
    }

    /**
     * 绑定手机号(静默还没写入用户信息)
     *
     * @param $phone
     * @param string $key
     *
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function bindind_phone($phone, string $key = '')
    {
        if (!$key) {
            throw new AdminException(410037);
        }
        [$openid, $wechatInfo, $spreadId, $login_type, $userType] = $createData = CacheService::getTokenBucket($key);
        if (!$createData) {
            throw new AdminException(410037);
        }
        $wechatInfo['phone'] = $phone;
        /** @var WechatUserServices $wechatUser */
        $wechatUser = app(WechatUserServices::class);
        //更新用户信息
        $user = $wechatUser->wechatOauthAfter([$openid, $wechatInfo, $spreadId, $login_type, $userType]);
        $token = $this->createToken((int) $user['uid'], $userType);
        if ($token) {
            return [
                'token' => $token['token'],
                'userInfo' => $user,
                'expires_time' => $token['params']['exp'],
            ];
        } else {
            return $this->fail(410019);
        }
    }

    /**
     * 用户绑定手机号
     *
     * @param int $uid
     * @param $phone
     * @param $step
     *
     * @return array
     */
    public function userBindindPhone(int $uid, $phone, $step)
    {
        $userInfo = $this->dao->get($uid);
        if (!$userInfo) {
            throw new AdminException(410113);
        }
        if ($this->dao->getOne([['phone', '=', $phone], ['user_type', '<>', 'h5'], ['is_del', '=', 0]])) {
            throw new AdminException(410039);
        }
        if ($userInfo->phone) {
            throw new AdminException(410040);
        }
        $data = [];
        if ($this->dao->getOne(['account' => $phone, 'phone' => $phone, 'user_type' => 'h5', 'is_del' => 0])) {
            if (!$step) {
                return ['msg' => 410041, 'data' => ['is_bind' => 1]];
            }
        } else {
            $data['account'] = $phone;
        }
        $data['phone'] = $phone;
        if ($this->dao->update($userInfo['uid'], $data, 'uid') || $userInfo->phone == $phone) {
            return ['msg' => 410016, 'data' => []];
        } else {
            throw new AdminException(410017);
        }
    }

    /**
     * 用户绑定手机号
     *
     * @param int $uid
     * @param $phone
     *
     * @return array
     */
    public function updateBindindPhone(int $uid, $phone)
    {
        $userInfo = $this->dao->get(['uid' => $uid, 'is_del' => 0]);
        if (!$userInfo) {
            throw new AdminException(410113);
        }
        if ($userInfo->phone == $phone) {
            throw new AdminException(410042);
        }
        if ($this->dao->getOne([['phone', '=', $phone], ['is_del', '=', 0]])) {
            throw new AdminException(410043);
        }
        $data = [];
        $data['phone'] = $phone;
        $data['account'] = $phone;
        if ($this->dao->update($userInfo['uid'], $data, 'uid')) {
            return ['msg' => 100001, 'data' => []];
        } else {
            throw new AdminException(100007);
        }
    }
}
