<?php

namespace App\Support\Services\Workerman\Chat;

use App\Support\Utils\Arr;
use App\Exceptions\AuthException;
use App\Services\User\UserServices;
use Illuminate\Support\Facades\Log;
use app\services\kefu\LoginServices;
use App\Services\User\UserAuthServices;
use Workerman\Connection\TcpConnection;
use App\Services\Order\StoreOrderServices;
use App\Services\Wechat\WechatUserServices;
use App\Support\Services\Workerman\Response;
use App\Services\Product\StoreProductServices;
use App\Support\Services\Wechat\WechatServices;
use app\services\kefu\service\StoreServicesLogServices;

/**
 * Class ChatHandle
 *
 * @package App\Support\Services\Workerman\chat
 */
class ChatHandle
{
    /**
     * @var ChatServices
     */
    protected $service;

    /**
     * ChatHandle constructor.
     *
     * @param ChatService $service
     */
    public function __construct(ChatService &$service)
    {
        $this->service = &$service;
    }

    /**
     * 客服登录
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     *
     * @return bool|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function kefu_login(TcpConnection &$connection, array $res, Response $response)
    {
        if (!isset($res['data']) || !$token = $res['data']) {
            return $response->close([
                'msg' => '授权失败!',
            ]);
        }
        try {
            /** @var LoginService $Service */
            $Service = app(LoginServices::class);
            $kefuInfo = $services->parseToken($token);
        } catch (AuthException $e) {
            return $response->close([
                'msg' => $e->getMessage(),
            ]);
        }

        $connection->kefuUser = $kefuInfo;
        /** @var UserService $userService */
        $userService = app(UserServices::class);
        $connection->user = $userService->get($kefuInfo['uid'], ['uid', 'nickname']);
        if (!isset($connection->user->uid)) {
            return $response->close([
                'msg' => '您登录的客服用户不存在',
            ]);
        }
        /** @var StoreServicesRecordService $service */
        $service = app(StoreServicesRecordServices::class);
        $service->updateRecord(['to_uid' => $connection->user->uid], ['online' => 1]);
        /** @var StoreServicesService $service */
        $service = app(StoreServicesServices::class);
        $service->update(['uid' => $connection->user->uid], ['online' => 1]);
        $this->service->setKefuUser($connection);

        return $response->success();
    }

    /**
     * 用户登录
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     *
     * @return bool|null
     */
    public function login(TcpConnection &$connection, array $res, Response $response)
    {
        if (!isset($res['data']) || !$token = $res['data']) {
            return $response->close([
                'msg' => '授权失败!',
            ]);
        }

        try {
            /** @var UserAuthService $Service */
            $Service = app(UserAuthServices::class);
            $authInfo = $Service->parseToken($token);
        } catch (AuthException $e) {
            return $response->close([
                'msg' => $e->getMessage(),
            ]);
        }

        $connection->user = $authInfo['user'];
        $connection->tokenData = $authInfo['tokenData'];
        $this->service->setUser($connection);

        /** @var StoreServicesRecordService $service */
        $service = app(StoreServicesRecordServices::class);
        $service->updateRecord(['to_uid' => $connection->user->uid], ['online' => 1, 'type' => $res['form_type'] ?? 1]);
        $connections = $this->service->kefuUser();
        foreach ($connections as &$conn) {
            if (!isset($conn->onlineUids) || !in_array($connection->user->uid, $conn->onlineUids ?? [])) {
                $response->connection($conn)->send('user_online', ['to_uid' => $connection->user->uid, 'online' => 1]);
            }
            if (!isset($conn->onlineUids)) {
                $conn->onlineUids = [];
            }
            array_push($conn->onlineUids, $connection->user->uid);
            $this->service->setKefuUser($conn, false);
        }

        return $response->connection($connection)->success();
    }

    /**
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     */
    public function to_chat(TcpConnection &$connection, array $res, Response $response)
    {
        $tourist_uid = $res['data']['tourist_uid'] ?? 0;
        if ($tourist_uid) {
            $connection->isTourist = true;
            $connection->user = (object) ['uid' => $tourist_uid];
            $connections = $this->service->user();
            if (!isset($connections[$tourist_uid])) {
                $this->service->setUser($connection);
            }
        }
        $connection->chatToUid = $res['data']['id'] ?? 0;
        if (isset($connection->user)) {
            $uid = $connection->user->uid;
            if ($connection->chatToUid && !isset($connection->isTourist)) {
                /** @var StoreServicesRecordService $service */
                $service = app(StoreServicesRecordServices::class);
                $service->update(['user_id' => $uid, 'to_uid' => $connection->chatToUid], ['mssage_num' => 0]);
                /** @var StoreServicesLogService $logService */
                $logService = app(StoreServicesLogServices::class);
                $logService->update(['uid' => $connection->chatToUid, 'to_uid' => $uid], ['type' => 1]);
            }
            $response->send('mssage_num', ['uid' => $connection->chatToUid, 'num' => 0, 'recored' => (object) []]);
        }
    }

    /**
     * 用户向客服发送消息
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     *
     * @return bool|null
     */
    public function chat(TcpConnection &$connection, array $res, Response $response)
    {
        $to_uid = $res['data']['to_uid'] ?? 0;
        $msn_type = $res['data']['type'] ?? 0;
        $msn = $res['data']['msn'] ?? '';
        $formType = $res['form_type'] ?? 0;
        //是否为游客
        $isTourist = $res['data']['is_tourist'] ?? 0;
        $tourist_uid = $res['data']['tourist_uid'] ?? 0;
        $isTourist = $isTourist && $tourist_uid;
        $tourist_avatar = $res['data']['tourist_avatar'] ?? '';
        $uid = $isTourist ? $tourist_uid : $connection->user->uid;
        if (!$to_uid) {
            return $response->send('err_tip', ['msg' => '用户不存在']);
        }
        if ($to_uid == $uid) {
            return $response->send('err_tip', ['msg' => '不能和自己聊天']);
        }
        /** @var StoreServicesLogService $logService */
        $logService = app(StoreServicesLogServices::class);
        if (!in_array($msn_type, $logService::MSN_TYPE)) {
            return $response->send('err_tip', ['msg' => '格式错误']);
        }
        $msn = trim(strip_tags(str_replace(["\n", "\t", "\r", "&nbsp;"], '', htmlspecialchars_decode($msn))));
        $data = compact('to_uid', 'msn_type', 'msn', 'uid');
        $data['add_time'] = time();
        $data['is_tourist'] = $res['data']['is_tourist'] ?? 0;
        $connections = $this->service->user();
        $online = isset($connections[$to_uid]) && isset($connections[$to_uid]->chatToUid) && $connections[$to_uid]->chatToUid == $uid;
        $data['type'] = $online ? 1 : 0;
        $data = $logService->save($data);
        $data = $data->toArray();
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = strtotime($data['add_time']);
        if (!$isTourist) {
            if (isset($this->service->kefuUser()[$data['uid']])) {
                /** @var StoreServicesService $userService */
                $userService = app(StoreServicesServices::class);
                $_userInfo = $userService->get(['uid' => $data['uid']], ['nickname', 'avatar']);
            } else {
                /** @var UserService $userService */
                $userService = app(UserServices::class);
                $_userInfo = $userService->getUserInfo($data['uid'], 'nickname,avatar');
            }
            $data['nickname'] = $_userInfo['nickname'];
            $data['avatar'] = $_userInfo['avatar'];
        } else {
            $avatar = sys_config('tourist_avatar');
            $_userInfo['avatar'] = $tourist_avatar ?: Arr::getArrayRandKey(is_array($avatar) ? $avatar : []);
            $_userInfo['nickname'] = '游客' . $uid;
            $data['nickname'] = $_userInfo['nickname'];
            $data['avatar'] = $_userInfo['avatar'];
        }

        //商品消息类型
        $data['productInfo'] = [];
        if ($msn_type == StoreServicesLogServices::MSN_TYPE_GOODS && $msn) {
            /** @var StoreProductService $productService */
            $productService = app(StoreProductServices::class);
            $productInfo = $productService->getProductInfo((int) $msn, 'store_name,IFNULL(sales,0) + IFNULL(ficti,0) as sales,image,slider_image,price,vip_price,ot_price,stock,id');
            $data['productInfo'] = $productInfo ? $productInfo->toArray() : [];
        }
        //订单消息类型
        $data['orderInfo'] = [];
        if ($msn_type == StoreServicesLogServices::MSN_TYPE_ORDER && $msn) {
            /** @var StoreOrderService $orderService */
            $orderService = app(StoreOrderServices::class);
            $order = $orderService->getUserOrderDetail($msn, $uid);
            if ($order) {
                $order = $orderService->tidyOrder($order->toArray(), true, true);
                $order['add_time_y'] = date('Y-m-d', $order['add_time']);
                $order['add_time_h'] = date('H:i:s', $order['add_time']);
                $data['orderInfo'] = $order;
            }
        }
        //给自己回复消息
        $response->send('chat', $data);

        //用户向客服发送消息，判断当前客服是否在登录中
        /** @var StoreServicesRecordService $serviceRecored */
        $serviceRecored = app(StoreServicesRecordServices::class);
        $unMessagesCount = $logService->getMessageNum(['uid' => $uid, 'to_uid' => $to_uid, 'type' => 0, 'is_tourist' => $isTourist ? 1 : 0]);
        //记录当前用户和他人聊天记录
        $data['recored'] = $serviceRecored->saveRecord($uid, $to_uid, $msn, $formType ?? 0, $msn_type, $unMessagesCount, $isTourist, $data['nickname'], $data['avatar']);
        //是否在线
        if ($online) {
            $response->connection($this->service->user()[$to_uid])->send('reply', $data);
        } else {
            //用户在线，可是没有和当前用户进行聊天，给当前用户发送未读条数
            if (isset($connections[$to_uid])) {
                $data['recored']['nickname'] = $_userInfo['nickname'];
                $data['recored']['avatar'] = $_userInfo['avatar'];
                $response->connection($this->service->user()[$to_uid])->send('mssage_num', [
                    'uid' => $uid,
                    'num' => $unMessagesCount,
                    'recored' => $data['recored'],
                ]);
            }
            if ($isTourist) {
                return true;
            }
            //用户不在线
            /** @var WechatUserService $wechatUserService */
            $wechatUserService = app(WechatUserServices::class);
            $userInfo = $wechatUserService->getOne(['uid' => $to_uid, 'user_type' => 'wechat'], 'nickname,subscribe,openid,headimgurl');
            if ($userInfo && $userInfo['subscribe'] && $userInfo['openid']) {
                $description = '您有新的消息，请注意查收！';
                if ($formType) {
                    $head = '客服接待消息提醒';
                    $url = sys_config('site_url') . '/kefu/mobile_chat?toUid=' . $uid . '&nickname=' . $_userInfo['nickname'];
                } else {
                    $head = '客服回复消息提醒';
                    $url = sys_config('site_url') . '/pages/extension/customer_list/chat?uid=' . $uid;
                }
                $message = WechatServices::newsMessage($head, $description, $url, $_userInfo['avatar']);
                $userInfo = $userInfo->toArray();
                try {
                    WechatServices::staffServices()->message($message)->to($userInfo['openid'])->send();
                } catch (\Exception $e) {
                    Log::error($userInfo['nickname'] . '发送失败' . $e->getMessage());
                }
            }
        }
    }

    /**
     * 上下线
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     */
    public function online(TcpConnection &$connection, array $res, Response $response)
    {
        $online = $res['data']['online'] ?? 0;
        $connections = $this->service->user();
        if (isset($connection->user->uid)) {
            $uids = $connection->user->uid;
            /** @var StoreServicesService $service */
            $service = app(StoreServicesServices::class);
            $service->update(['uid' => $uids], ['online' => $online]);
            //广播给正在和自己聊天的用户
            foreach ($connections as $uid => $conn) {
                if ($uid !== $uids && $uids == ($conn->chatToUid ?? 0)) {
                    $response->connection($conn)->send('online', ['online' => $online, 'uid' => $uids]);
                }
            }
        }
    }

    /**
     * 客服转接
     *
     * @param TcpConnection $connection
     * @param array $res
     * @param Response $response
     */
    public function transfer(TcpConnection &$connection, array $res, Response $response)
    {
        $data = $res['data'] ?? [];
        $uid = $data['recored']['uid'] ?? 0;
        if ($uid && $this->service->user($uid)) {
            $data['recored']['online'] = 1;
        } else {
            $data['recored']['online'] = 0;
        }
        $response->send('transfer', $data);
    }
}
