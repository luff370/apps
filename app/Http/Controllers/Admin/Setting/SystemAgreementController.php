<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use App\Services\Other\AgreementServices;

class SystemAgreementController extends Controller
{
    /**
     * 构造方法
     * SystemCity constructor.
     *
     * @param AgreementServices $services
     */
    public function __construct(AgreementServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取协议内容
     *
     * @param $type
     */
    public function getAgreement($type)
    {
        if (!$type) {
            return $this->fail(400184);
        }
        $info = $this->service->getAgreementBytype($type);

        return $this->success($info);
    }

    /**
     * 保存协议内容
     */
    public function saveAgreement()
    {
        $data = $this->getMore([
            ['id', 0],
            ['type', 0],
            ['title', ''],
            ['content', ''],
        ]);
        $data['status'] = 1;
        $this->service->saveAgreement($data, $data['id']);

        return $this->success(100000);
    }
}
