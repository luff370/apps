<?php

namespace App\Support\Traits;

use Carbon\Carbon;
use App\Models\Order\OrderLogistic;
use App\Support\Services\HttpService;
use Illuminate\Support\Facades\Config;

/**
 * trait Express
 */
trait ExpressTrait
{
    /**
     * @var string[]
     */
    protected static $api = [
        'autonumber' => 'https://www.kuaidi100.com/autonumber/auto',
        'query' => 'https://poll.kuaidi100.com/poll/query.do',
    ];

    /**
     * 自动识别快递公司信息
     *
     * @param string $num
     *
     * @return mixed
     */
    public function expressAutonumber(string $num)
    {
        $params = [
            'key' => Config::get('express.kuaidi100.key', ''),
            'num' => $num,
        ];
        $res = HttpService::getRequest(self::$api['autonumber'], http_build_query($params));
        $res = json_decode($res, true);

        return $res[0] ?? [];
    }

    /**
     * 物流信息查询
     *
     * @param string $num
     * @param string $com
     * @param string $phone
     *
     * @return mixed
     */
    public function expressQuery(string $num = '', string $com = '', $phone = '')
    {
        //参数设置
        $key = Config::get('express.kuaidi100.key', '');                              // 客户授权key
        $customer = Config::get('express.kuaidi100.customer', '');;                   // 查询公司编号
        $param = [
            'com' => $com,                    // 快递公司编码
            'num' => $num,                    // 快递单号
            'phone' => $phone,                // 手机号
            'from' => '',                     // 出发地城市
            'to' => '',                       // 目的地城市
            'resultv2' => '4',                // 开启行政区域解析
            'show' => '0',                    // 返回格式：0：json格式（默认），1：xml，2：html，3：text
            'order' => 'desc'                 // 返回结果排序:desc降序（默认）,asc 升序
        ];

        //请求参数
        $post_data = [];
        $post_data['customer'] = $customer;
        $post_data['param'] = json_encode($param, JSON_UNESCAPED_UNICODE);
        $sign = md5($post_data['param'] . $key . $post_data['customer']);
        $post_data['sign'] = strtoupper($sign);

        $res = HttpService::postRequest(self::$api['query'], http_build_query($post_data));
        $res = json_decode($res, true);

        logger()->info('express query', [$num, $com, $phone, $res]);

        if (empty($res['data'])) {
            return [];
        }

        return $res;
    }

    /**
     * 物流公司查询
     *
     * @param string $expressNum
     * @param string|null $com
     * @param string $phone
     *
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Exception
     */
    public function expressQueryByCache(string $expressNum, string $com = null, $phone = ''): array
    {
        $cacheKey = config('express.cache_prefix') . "$expressNum-$com-$phone";

        $data = cache()->get($cacheKey);
        if (empty($data)) {
            if (!empty($phone) && strlen($phone) > 4) {
                $phone = substr($phone, -4);
            }

            $data = $this->expressQuery($expressNum, $com, $phone);
            if (empty($data)) {
                $comInfo = $this->expressAutonumber($expressNum);
                if (!empty($comInfo['comCode'])) {
                    $data = $this->expressQuery($expressNum, $comInfo['comCode'], $phone);
                }
            }

            if (empty($data)) {
                return [];
            }

            cache()->set($cacheKey, $data, config('express.cache_ttl'));
        }

        return $data;
    }

    /**
     * 物流状态转换为本地状态
     *
     * @param $state
     *
     * @return int
     */
    public function expressStateConvert($state, $statusCode = 0): int
    {
        if ($state > 14) {
            // 驿站存放超时，暂不作为疑难处理
            if ($state == 205 && $statusCode == 501) {
                $state = 501;
            }
            $state = substr($state, 0, 1);
        }

        switch ($state) {
            case 2: // 疑难
                $newState = OrderLogistic::StateKnotty;
                break;
            case 3: // 签收
                $newState = OrderLogistic::StateReceived;
                break;
            case 4: // 退签
            case 14: // 拒签
                $newState = OrderLogistic::StateRejection;
                break;
            default: // 其余按在途逻辑
                $newState = OrderLogistic::StateDelivery;
        }

        return $newState;
    }

    /**
     * 物流信息异常检测
     *
     * @param $state
     * @param $content
     * @param $time
     *
     * @return string
     */
    public function expressAnomalyCheck($state, $statusCode, $content, $time): string
    {
        $anomaly = '';

        // 已签收或已拒收
        if (in_array($state, [OrderLogistic::StateReceived, OrderLogistic::StateRejection])) {
            return '';
        }

        // 投柜或驿站
        if (in_array($statusCode, [501, 205])) {
            return '';
        }

        if (Carbon::now()->diffInHours(Carbon::parse($time)) > 48) {
            $anomaly = '物流信息48小时未更新';
        } elseif (mb_strpos($content, '地址不详') !== false) {
            $anomaly = '地址不详';
        }

        return $anomaly;
    }

    public function pickDeliveryAddress(string $expressCode, string $info): string
    {
        switch ($expressCode) {
            case 'zhongtong'://中通快递
                // // "包裹将继续由【xxx】为您代为保管"
                // $startLen = mb_strlen('包裹将继续由【');
                // $startPos = mb_strpos($info, '包裹将继续由【');
                // $endPos = mb_strpos($info, '】为您代为保管');
                // if ($startPos && $endPos) {
                //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
                // }

                // 快件已在 xx驿站 的【xxx】暂放
                $startLen = mb_strlen('的【');
                $startPos = mb_strpos($info, '的【');
                $endPos = mb_strpos($info, '】暂放');

                if ($startPos && $endPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }

                break;
            case 'yunda'://韵达快递
                // 您的快件已暂存至 xxx，地址：
                $startLen = mb_strlen('暂存至');
                $startPos = mb_strpos($info, '暂存至');
                $endPos = mb_strpos($info, '，地址');
                if (empty($endPos)) {
                    $endPos = mb_strpos($info, '，请及时领取签收');
                }

                if ($startPos && $endPos) {
                    return trim(str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen)));
                }

                // // "包裹将继续由【xxx】为您代为保管"
                // $startLen = mb_strlen('包裹将继续由【');
                // $startPos = mb_strpos($info, '包裹将继续由【');
                // $endPos = mb_strpos($info, '】为您代为保管');
                //
                // if ($startPos && $endPos) {
                //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
                // }

                break;

            case 'youzhengguonei'://邮政快递包裹
                // "您的快件已派送至xxx，自提点电话:xxx"
                $startLen = mb_strlen('已派送至');
                $startPos = mb_strpos($info, '已派送至');
                $endPos = mb_strpos($info, '，', $startPos);

                if ($startPos && $endPos && $endPos > $startPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }

                break;

            case 'yuantong'://圆通速递
                // 包裹将继续由[xxx]为您代为保管
                $startLen = mb_strlen('包裹将继续由[');
                $startPos = mb_strpos($info, '包裹将继续由[');
                $endPos = mb_strpos($info, ']为您代为保管');

                if ($startPos && $endPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }

                // 您的快件已到达xxx，请及时取件
                $startLen = mb_strlen('快件已到达');
                $startPos = mb_strpos($info, '快件已到达');
                $endPos = mb_strpos($info, '，');

                if ($startPos && $endPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }

                // // 您的快件已暂存至xxx
                // $startLen = mb_strlen('已暂存至');
                // $startPos = mb_strpos($info, '已暂存至');
                // $endPos = mb_strpos($info, '，', $startPos);
                //
                // if ($startPos && $endPos && $endPos > $startPos) {
                //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
                // }

                // // 快件已由xxx代收
                // $startLen = mb_strlen('快件已由');
                // $startPos = mb_strpos($info, '快件已由');
                // $endPos = mb_strpos($info, '代收');
                //
                // if ($startPos && $endPos && $endPos > $startPos) {
                //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
                // }

                break;

            case 'shentong': //申通快递
                // 您的包裹已放入xxx，请您尽快xxx
                $startLen = mb_strlen('包裹已放入');
                $startPos = mb_strpos($info, '包裹已放入');
                $endPos = mb_strpos($info, '，请您尽快');

                if ($startPos && $endPos && $endPos > $startPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }

            // // 快件已暂存至xxx，如有疑问请联系xxx
            // $startLen = mb_strlen('已暂存至');
            // $startPos = mb_strpos($info, '已暂存至');
            // $endPos = mb_strpos($info, '，如有疑问');
            //
            // if ($startPos && $endPos && $endPos > $startPos) {
            //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
            // }

            // 快件已被xxx代收，请及时取件
            // $startLen = mb_strlen('快件已被');
            // $startPos = mb_strpos($info, '快件已被');
            // $endPos = mb_strpos($info, '代收');
            //
            // if ($endPos && $endPos > $startPos) {
            //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
            // }

            // // [驿站]您的包裹已存放至xxx
            // $startLen = mb_strlen('已存放至');
            // $startPos = mb_strpos($info, '已存放至');
            // $endPos = mb_strpos($info, '，');
            //
            // if ($startPos && $endPos && $endPos > $startPos) {
            //     return mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen);
            // }

            case 'jtexpress': //极兔速递
                // 记得早点来xxx取它回家！
                $startLen = mb_strlen('记得早点来');
                $startPos = mb_strpos($info, '记得早点来');
                $endPos = mb_strpos($info, '取它回家');

                if ($startPos && $endPos && $endPos > $startPos) {
                    return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
                }
        }

        // 快件已暂存至xxx，如有疑问请联系xxx
        $startLen = mb_strlen('已暂存至');
        $startPos = mb_strpos($info, '已暂存至');
        $endPos = mb_strpos($info, '，', $startPos);
        if ($startPos && $endPos && $endPos > $startPos) {
            return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
        }

        // "包裹将继续由【xxx】为您代为保管"
        $startLen = mb_strlen('包裹将继续由【');
        $startPos = mb_strpos($info, '包裹将继续由【');
        $endPos = mb_strpos($info, '】为您代为保管');

        if ($startPos && $endPos) {
            return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
        }

        // 包裹已存放至xxx
        $startLen = mb_strlen('已存放至');
        $startPos = mb_strpos($info, '已存放至');
        $endPos = mb_strpos($info, '，', $startPos);

        if ($startPos && $endPos && $endPos > $startPos) {
            return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
        }

        // 快件已被xxx代收，请及时取件
        $startLen = mb_strlen('快件已被');
        $startPos = mb_strpos($info, '快件已被');
        $endPos = mb_strpos($info, '代收');

        if ($endPos && $endPos > $startPos) {
            return str_replace(['【', '】'], '', mb_substr($info, $startPos + $startLen, $endPos - $startPos - $startLen));
        }

        return str_replace(['【', '】'], '', $info);
    }
}
