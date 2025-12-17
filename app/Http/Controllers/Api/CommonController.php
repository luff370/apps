<?php

namespace App\Http\Controllers\Api;

use App\Services\App\AppsService;
use Ramsey\Uuid\Uuid;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\Services\AppConfigService;
use App\Services\User\UserWhitelistService;
use App\Services\System\AppVersionServices;
use App\Services\User\UserAccessLogService;
use App\Support\Services\SystemConfigService;

class CommonController extends Controller
{
    public function appInfo(AppsService $appsService, Request $request)
    {
        // logger()->info('请求信息：',['header'=>$request->headers, 'body'=>$request->all()]);

        // 用户数
        $data['active_users'] = 34099;
        // 用户协议
        $data['user_agreement'] = url('agreement/user', ['app_id' => $this->getAppId(), 'platform' => $this->getMarketChannel()]);
        // 隐私政策
        $data['privacy_agreement'] = url('agreement/privacy', ['app_id' => $this->getAppId(), 'platform' => $this->getMarketChannel()]);

        // 应用基础配置(后期应用已废弃此配置项)
        $sysConfig = SystemConfigService::getAppConfigs($this->getAppId());
        if (!empty($sysConfig)) {
            $data = array_merge($data, $sysConfig);
        }

        // 应用信息配置(应用管理的基础配置，后期应用使用这里的配置信息)
        $appInfo = $appsService->getAppConfig($this->getAppId());
        if (!empty($appInfo)) {
            $data = array_merge($data, $appInfo);
        }

        // 应用渠道版本配置(具有最高优先级、会覆盖基础配置)
        $appConfig = AppConfigService::getConfigsByAppIdChannelVersion($this->getAppId(), $this->getMarketChannel(), $this->getAppVersion());
        if (!empty($appConfig)) {
            $data = array_merge($data, $appConfig);
        }

        // 字符类型数值转换为int类型
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $data[$key] = (int) $value;
            }
        }

        // 订阅页面AB页面展示概率计算
        if (!empty($data['ABtest_subscribe'])) {
            $data['subscribe_page'] = 'page' . computeProbability($data['ABtest_subscribe']);
        }

        // 当前版本是否为审核状态
        $data['is_audit'] = AppVersionServices::getAuditStatusByVersion($this->getAppId(), $this->getMarketChannel(), $this->getAppVersion());

        // 白名单默认状态
        $userWhiteList = UserWhitelistService::conversionTypeToArr(0);

        // 自动添加默认的ip白名单
        if (!empty($data['auto_add_white_list']) && $this->getMarketChannel() != 'ios') {
            // 白名单默认状态
            $userWhiteList = UserWhitelistService::conversionTypeToArr($data['auto_add_white_list']);
            // 添加IP白名单
            UserWhitelistService::createByIp($request->getClientIp(), $data['auto_add_white_list'], '', 3, $this->getAppId(), $this->getMarketChannel(), $this->getAppVersion(), $this->getDevice());
            // 添加设备白名单
            UserWhitelistService::createByDevice($this->getDevice(), $data['auto_add_white_list'], '', 3, $this->getAppId(), $this->getMarketChannel(), $this->getAppVersion());
            // 记录访问日志
            UserWhitelistService::recordWhitelistUserAccessLog($this->getAppId(), $this->getPlatform(), $this->getMarketChannel(), ip2region($this->getClientIp()), $this->getClientIp(), $this->getDevice(), $this->getAppVersion(), $this->getUuid());
        } else {
            // 判断白名单是否开启
            if (!empty($data['user_white_list_filter']) && $this->getMarketChannel() != 'ios') {
                $userWhiteList = $this->__getUserWhiteList();
            }
        }
        $data = array_merge($data, $userWhiteList);

        // 记录访问日志
        UserAccessLogService::record(0, $this->getAppId(), $this->getMarketChannel(), $this->getAppVersion(), $this->getOsVersion(), $this->getUuid(), $this->getDevice(), $this->getClientIp(), $request->path(), $data);

        // logger()->info('返回信息：', $data);

        return $this->success($data);
    }

    private function __getUserWhiteList(): array
    {
        $ip = $this->getClientIp();
        $region = ip2region($ip);
        $whitelistType = UserWhiteListService::getUserWhiteInfo(
            $this->getAppId(),
            $this->getPlatform(),
            $this->getMarketChannel(),
            $region,
            $ip,
            $this->getDevice(),
            $this->getAppVersion(),
            $this->getUuid()
        );

        logger()->info('用户白名单信息：', [
            'appId' => $this->getAppId(),
            'platform' => $this->getPlatform(),
            'marketChannel' => $this->getMarketChannel(),
            'region' => $region,
            'ip' => $ip,
            'device' => $this->getDevice(),
            'appVersion' => $this->getAppVersion(),
            'uuid' => $this->getUuid(),
            'whitelistType' => $whitelistType,
        ]);

        return UserWhitelistService::conversionTypeToArr($whitelistType);
    }

    public function getGroupData($groupName)
    {
        return $this->success(sys_data($groupName));
    }

    public function fileUpload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return $this->fail('缺少上传的文件');
        }

        $extension = $request->file->extension();
        $fileName = Uuid::uuid1()->toString() . '.' . $extension;

        if ($userId = authUserId()) {
            $fileName = "$userId/" . $fileName;
        }

        if ($appId = $this->getAppId()) {
            $fileName = "$appId/" . $fileName;
        }

        Storage::drive('public');
        $savePatch = 'file/' . $fileName;
        $result = Storage::put($savePatch, fopen($request->file, 'r'));
        if ($result === false) {
            return $this->fail('上传失败');
        }

        return $this->success(['url' => Storage::url($savePatch)]);
    }

    public function withdrawalUsersShow(Request $request)
    {
        $total = $request->get('num', 20);
        if ($total > 100) {
            $total = 100;
        }

        $amountArr = [
            0 => 0.01,
            1 => 0.01,
            2 => 0.01,
            3 => 0.01,
            4 => 0.01,
            5 => 0.01,
            6 => 0.01,
            7 => 0.01,
            8 => 0.01,
            9 => 0.01,
            10 => 100,
            11 => 100,
            12 => 100,
        ];

        $list = [];
        for ($i = 1; $i <= $total; $i++) {
            $list[] = rand(1500, 1989) . "****" . rand(1250, 9999) . "成功兑换" . ($amountArr[rand(0, 12)] ?? 0.01) . "元";
        }

        return $this->success(['list' => $list]);
    }

    public function saveDeviceToken(Request $request)
    {
        $uuid = $this->getUuid();
        if (empty($uuid)) {
            return $this->fail('缺少uuid');
        }

        $tokenData = $request->all(['u_token', 'j_token']);
        $tokenData = array_filter($tokenData);
        if (!empty($tokenData)) {
            DeviceToken::query()->updateOrCreate(['uuid' => $uuid], $tokenData);
        }

        return $this->success();
    }

    public function avatar(): \Illuminate\Http\JsonResponse
    {
        $data = [
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTIPfZkaX9iaajTK3aTtLs6PnLuxSUNRuhVVldmXhzfyibglYhlE1c86NfFiatUkGICynIAliaYNk0pU3g/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83eo9yBquq2P0N6JA8BWpDPcwsvqvjqW61A3xDI1XtpS4ORGXnmyxEOTMVDCL8RHH6IlCPiaHdMzRvaA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/PiajxSqBRaEKQibn08KL7ibqgjAmLt8Ep9zFxOoztjPPs6LTFQV0hYSiaBicxCVk3GiaVLu1tQBYgMicscy46WdRScC4Q/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTLiaYmXRPV19PvCGic498bzIiaopraqziaoUZ9z4mQZaHibjRzxQLDjQ8micFMwr2xe2ic4X5u0p2V3aX9hw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTKMutyODEjgsOk2BHFhLxR66jlBxhnPInHct8fhv9OmvgMkthNdwiaWuB32hgdpcddLQmfq1NsM9Zg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTLNGyaj31T6YTTFwNgWHflBdAAnMhA2cqWNgx3m4hIYVaWibLTyiaqOiasYpF1NibWHpSzbojGib0l5Wvw/132",
            "https://thirdwx.qlogo.cn/mmhead/P3cxrKeRC8x4J3z0ep82w3oSDRKblSuBK3CaBya0XdE/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83epNibTuRPEUkxfo72aAWvXuw9XFYXEjkUNLG0QkLZRRibubmnhcfsKc7ib8AlVS6ITpJzx0wUGN1eR8w/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTKek09Jl8NTu59sELacmXfFAVUSM8RQX2IJtGHTknyiczfpEQJTrT3UsibkESicHZYlltArkCTbnALSA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTLJ2icxpgJEXWoLFkiafCCTrLOYx84wuuwhVDHTaBiaN1Pcr7ge6nVrKO5casEscbvq6bS1EyrCwZx7w/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTJjUAIicI0k90eJrg4HPGAdrDBvrWKDG2BYQuyibjDWU97vAXXBibpKwN33GAQpevkTDWQa6Mpwvic4Cg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTJEOa0DnN4LECCHrnYVDaUBibNRPNANk53d4ibwTaExv4lxQVekjvrKZXPYSf8QGclclNKFu55EtKNA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/UEiazaTGdl4HbqVTza2CZib075A36xJjoErMnV0Of5Dt0llDe3Q7FIgPxeUCK1qibGrsmcDicfjt0xKWURtGEByDcQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/pyzYxicv6UhXXlibiaHuJgb7VPXiaxcGRzhd4iaeZDaSbEBrlO6lw4OrsBGDT0Hzu2LhtDa6kvbTL6BcvAhptrMtBAQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/88DtYRuOEBkowDwBaOzhicn10hB5DoWVZm6LG64JA9HNkIMNq8LEFzMvViaJSS5skIibIMzxVg6F0QXfD4Dc88zNw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/C3DHCUWr60FMKPCjpyMiaZaTC30HlmmFAdg9OlCO1yOxqAGWWK0VTW2BJb96vdhHWLb5AuPDETgcibia1ZDmulYxw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/jdcEibbu6EicFhjaicy2tqiczQBibYV9EWbCCJ04C3kWEU7zp2AOqSh7DMiasnXpONYXibOqQVqABlPvic6FmxP36Tkia1g/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTJQ8ykWbKalHicR0bsDlOc3FOxEkd0QTcUhAVQxfOCveRKPLjpcXTxSZJJsXTmnCbiawZ3iampNTlU2w/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/7I0Rd45OAZUK0D0JBia6q9e3NW80reSicP8IpljRbzAvKPMRza4feYD5jzOcPaugPzddjxqOtHKo6VlvuPS4YufQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/YsGBcc3ZDjXbyQ1vn0W3lWMakhxnMyd80mhFelPsgJribwhCUPWC34kMP3mCnly0mgvarwxia4nnYEo5jXhhm38A/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/FKowOLZJnQrqGFlXxib5UdnfjkwEtAzicyW6xbyxafYMicr7iaO8zmSh7Y72uVSAKchDfCDibVuQicBu9TKuykPDfqAw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/qNic94bvUA9GydofyDObv99lZS5uZSLLuicoR84iaHS0W6uju52CT9qNencuDXeSaU3wL78bWickUjewtSjmRxx4icA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/ygBibyM8xBCX6HpMbTDfe6hpBicMwlVhzmCdweiaiaGrSBkBB6FEGibHdhBicBxbCVtyKD06WviaQYlj5cmgrRbXkMyicw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Y4cEZmb3oJU1zl7Y7RU2jxicEubaAAjYKHKTZIfA7LoQvFW9ugxia0ZtgOwnupRDWy4uEBCXQvEiavPtsYdcIaocg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83epf5jF8Kuft209LDMCaJ06zYqn7X13flYiaKXVOdXR9VVy1IIoukic81sPcjKDF6l4DGbvib2fiaXozng/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/6ADVImAiaZzuIiaIOlfVrlYynJ9s4w9e7DnFrI1jkhk5iaGYApCkFyNNjrhlteJZdTpMEMEibcnUtZFHNQuLJPiaxAA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/8r7SsWjzficsSo3vtqmrdcN2IvQBUxz4BYXGicY9cEfdUksvZbbW5REBjjObb5s8QSo77icPFicV5QQ3UbPgdLEMCw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTINBL0Cfna2WzWgn24vpcKL8zgibB8mdjWic8jCdTAGtR9kChic4QtznVziaZFlInC5OfJLGSQeMnletw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83erXHBvoib3jRYUs3DhXxrewLpcxyUhJSrozz27KVlwRxp9SicCz1CiaZyYGZFqrANEKc19QsL9lWBHGQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/yaywFdfM3rRiakicD79HicVUWeWpzj16eVGOIxB3s53tBW3AMpEegHXzyQ2u4OT99jPahnj0gnrTJc8WjickD8NE5w/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTKMaJZK3Fpp3yHcl8icSffbxoz9rzLVrMHIicexRZE07Wznxv2LePVvB5AFqicWwhZwdTZOYicarqeM6A/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83eosibfWppIdcUw91keicR2zhw9Ju2bLsHXHN2qFGkfvWiaibHbHrekzAlyIwSb1Drq3wO7icw3TkKNib1ZQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/pxRkugrFcaPvCauDgKR4icR6p4xBbrBUvOkRXkLTEaNZzgrbnbaqjNmOaLOwq1kafX42qXmbRQwibf0nGIUDUm3w/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83erJGgialqYTvfk7icIkRyo5OnF52ibYq0ict7tRpx1aW3YNwrmcBczzgoU01InuhAgE2VkQRaGsUwyAoA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/TabvouQbVRhMWr7vicJN94lGHNzd62hXApmGlNrVianseW2DGp5aJCacdTGtbDsQjlMp2ns7ib5quAIAnU2ypg4Fg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTLDd0KEOq9SZwW5k5lOCfPNyH23Xoc9IryL13Atpx8PIU9hOgtd7E702XQicZm52AJvO4Bkq8DDsKA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/9OopBia9sicudd9Zymdtia5kWaZTjSExULm4IOneia25WjnAraNHKBJIj51hd03enhdaxeoWN0sdc6NM8WNQdIqzSw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/E9ngLh0KSnSVic1OSUTZbb4adyXwSsGz4uDPLZINibjNA9xhkTkGtVfOfb86JwPyrNAgicYsn1ia9DSibvSCADrIvkw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/8nsklc0ARyFr5d1BWVKWoicCqbN1iaCQiazGMnZdQu2XJ79fILriaNZBeZ374R4Gs2Xq4e5kFGbNg304AvXDkib8DiaQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83eqjR2c1rJmtuicdTRhXv4ngFTicI3ByLf6WgMNfOrcCUW8BHviczgk4NeM5yfpOcO8xArThGf6DeSgNw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83epmCHCkPb1bo3KKsMzYQXJZibWv9ibJxzQCkgudqJJwvkVwfBHLbFUU85Mm1fOKt3BC4IMDxb616pHg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTIwubhdl7nohC2TGPeiaDQZyjNcAx9kegd6LbcgPHHEMRnwg2oVNbdoIe7MGHxk4ia1U4B3lx3bYedw/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/onPEDvT2EUqiaiaicnQfHLDBGFu1dpN0URfWMbuQmVnO2mkKIHmQ71pkl3QSo8ibFExO9SszpZoacibLs0XLITicrJeg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/jKVBpmgWtFR8pRjnHhjCeKj6wRqf3IWtkUWpXaic0HRXKaOMIibO5GFkQLicc7PKUrSPx9bicWGpYTRDMobsunemAA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83eqDCYdARDHGkNTYw3BTuTrKBve0GP3zjYbm5UYAFho7TbiaNaJgGaibQujbX32bvQSzY8uiahICOpszQ/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Mjic22BWKGSm1Kn65AXicBWq0CGLeDW97Rhz3WCTVIt1zET7iaKSrdggnnposTFzVTzBIcW6PlXYl4LdUlRhBklibg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTK6dWl2QSICY2kbnomGVEYISiaYLyU9m515yibibdbJllhvk6lNV4LbBlGsCxMesEPamFtfKMPQtvFWA/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTIoq1ww2kOdoFInQHOP1BYsOicwTmz9hVhoQmsiayObv1eRQdChKfNnTicyBMO0nBicyFoppOic3qCkEQg/132",
            "https://thirdwx.qlogo.cn/mmopen/vi_32/O2SmtCNrsViclFrykXS2R6E1DKVc2z1RE5mVGyp2E6WOT0QDV3XWWmrYKicI6Zf6qSGpgXyhicXnN0iazwgFSceMVA/132",
        ];

        return $this->success($data);
    }
}
