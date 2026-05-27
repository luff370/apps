<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\AdAccessLogService;

/**
 * 广告访问日志
 */
class AdAccessLogController extends Controller
{
    public function __construct(AdAccessLogService $service)
    {
        $this->service = $service;
    }

    /**
     * 广告访问统计
     */
    public function stat()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['market_channel', ''],
            ['version', ''],
            ['ad_type', ''],
            ['ad_index', ''],
            ['ad_channel', ''],
            ['time', ''],
        ]);

        return $this->success($this->service->getStatByPage($filter));
    }

    /**
     * 广告请求明细
     */
    public function index()
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['market_channel', ''],
            ['version', ''],
            ['ad_type', ''],
            ['ad_index', ''],
            ['ad_channel', ''],
            ['status', ''],
            ['user_id', ''],
            ['uuid', ''],
            ['ad_id', ''],
            ['ad_code', ''],
            ['keyword', ''],
            ['time', ''],
        ]);

        $fields = [
            'id',
            'app_id',
            'market_channel',
            'version',
            'user_id',
            'uuid',
            'ad_id',
            'ad_code',
            'ad_type',
            'ad_channel',
            'ad_index',
            'status',
            'error_code',
            'error_msg',
            'created_at',
        ];

        $data = $this->service->getAllByPage($filter, $fields, ['id' => 'desc']);

        return $this->success($data);
    }
}
