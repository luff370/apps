<?php

namespace App\Http\Controllers\Admin\System;

use App\Models\System\SmsRecord;
use App\Http\Controllers\Admin\Controller;
use App\Services\System\SmsRecordService;

/**
 * 短信记录表控制器
 * Class SystemLog
 *
 * @package App\Http\Controllers\Admin\System
 */
class SmsRecordController extends Controller
{
    public function __construct(SmsRecordService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $filter = $this->getMore([
            ['pages', ''],
            ['type', ''],
            ['keyword', ''],
            ['data', ''],
        ]);
        $filter['add_time'] = $filter['data'];

        $data = $this->service->getAllByPage($filter);
        if (!empty($data['list'])) {
            $typeNames = SmsRecord::typeMap();
            foreach ($data['list'] as &$item) {
                $item['type_name'] = $typeNames[$item['type']] ?? '';
            }
        }

        return $this->success($data);
    }

}

