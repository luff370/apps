<?php

namespace App\Http\Controllers\Api;

use App\Models\AdAccessLog;
use App\Services\App\AdAccessLogService;
use App\Services\App\AdvertisementService;
use App\Support\Services\Advertisement;
use App\Support\Utils\Token;
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
        $appId = (int)$this->getAppId();
        if ($appId <= 0) {
            return $this->fail('缺少应用ID参数');
        }

        $userId = 0;
        $token = (string)$request->header('Token', '');
        if ($token !== '') {
            try {
                $tokenData = Token::verify($token);
                $userId = (int)($tokenData['user_id'] ?? 0);
            } catch (\Throwable $e) {
                $userId = 0;
            }
        }
        $status = $request->post('status', null);
        $errorCode = (string)$request->post('error_code', '');
        $errorMsg = (string)$request->post('error_msg', '');
        if ($status === null || $status === '') {
            $status = ($errorCode !== '' || $errorMsg !== '') ? AdAccessLog::STATUS_FAIL : AdAccessLog::STATUS_SUCCESS;
        }

        AdAccessLogService::record([
            'app_id' => $appId,
            'market_channel' => (string)$this->getMarketChannel(),
            'version' => (string)$this->getAppVersion(),
            'user_id' => $userId,
            'uuid' => (string)$this->getUuid(),
            'ad_id' => (int)$request->post('ad_id', 0),
            'ad_code' => (string)$request->post('ad_code', ''),
            'ad_type' => (string)$request->post('ad_type', ''),
            'ad_channel' => (string)$request->post('ad_channel', ''),
            'ad_index' => (string)$request->post('ad_index', ''),
            'status' => (int)$status,
            'error_code' => $errorCode,
            'error_msg' => $errorMsg,
        ]);

        return $this->success();
    }

}
