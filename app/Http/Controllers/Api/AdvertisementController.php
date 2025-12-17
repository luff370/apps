<?php

namespace App\Http\Controllers\Api;

use App\Services\App\AdvertisementService;
use App\Support\Services\Advertisement;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function __construct(AdvertisementService  $advertisementService)
    {
        $this->service = $advertisementService;
    }

    // 根据应用和渠道获取配置的广告信息
    public function list(): \Illuminate\Http\JsonResponse
    {
        $appId = $this->getAppId();
        $marketChannel = $this->getMarketChannel();

        return $this->success(Advertisement::getAdvertisementsByAppIdChannel($appId, $marketChannel));
    }

    // 记录广告访问日志
    public function stat(Request $request): \Illuminate\Http\JsonResponse
    {

        return $this->success();
    }

}
