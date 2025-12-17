<?php

namespace App\Http\Controllers\Admin\App;

use App\Models\AppPayment;
use App\Services\App\PaymentService;
use App\Http\Controllers\Admin\Controller;

/**
 * 应用支付管理
 */
class PaymentController extends Controller
{
    public function __construct(PaymentService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取应用支付列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['status', ''],
        ]);
        $data = $this->service->getAllByPage($where, ['*'], ['id' => 'desc'], ['app', 'payment']);

        return $this->success($data);
    }


    /**
     * 保存新建应用支付
     */
    public function store()
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['mch_id', ''],
            ['pay_app_id', ''],
            ['pay_channel', ''],
            ['pay_type', ''],
            ['return_url', ''],
            ['remark', ''],
            ['status', 1],
        ]);
        if ($data['pay_channel'] == AppPayment::PayChannelAlipay){
            $data['pay_app_id'] = $data['mch_id'];
        }

        $this->service->save($data);

        return $this->success('保存成功');
    }

    public function show($id)
    {
        $data = $this->service->getRow($id);

        return $this->success($data);
    }

    /**
     * 删除应用支付
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 修改应用支付状态
     */
    public function setStatus($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        return $this->success(100014);
    }

    public function copy($id)
    {
        $info = $this->service->get($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        AppPayment::query()->create(
            [
                'app_id' => $info['app_id'],
                'pay_app_id' => $info['pay_app_id'],
                'mch_id' => $info['mch_id'],
                'pay_type' => $info['pay_type'],
                'pay_channel' => $info['pay_channel'],
                'return_url' => $info['return_url'],
                'notify_url' => $info['notify_url'],
                'status' => 0,
            ]
        );

        return $this->success(100021);
    }
}
