<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserRechargeServices;

/**
 * Class UserRecharge
 *
 * @package App\Http\Controllers\Admin\Finance
 */
class UserRecharge extends Controller
{
    /**
     * UserRecharge constructor.
     *
     * @param UserRechargeServices $services
     */
    public function __construct(UserRechargeServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['data', ''],
            ['paid', ''],
            ['nickname', ''],
        ]);

        return $this->success($this->service->getRechargeList($where));
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->delRecharge((int) $id) ? 100002 : 100008);
    }

    /**
     * 获取用户充值数据
     *
     * @return array
     */
    public function user_recharge()
    {
        $where = $this->getMore([
            ['data', ''],
            ['paid', ''],
            ['nickname', ''],
        ]);

        return $this->success($this->service->user_recharge($where));
    }

    /**
     * 退款表单
     *
     * @param $id
     * |void
     */
    public function refund_edit($id)
    {
        if (!$id) {
            return $this->fail(100026);
        }

        return $this->success($this->service->refund_edit((int) $id));
    }

    /**
     * 退款操作
     *
     * @param $id
     */
    public function refund_update($id)
    {
        $data = $this->getMore([
            'refund_price',
        ]);
        if (!$id) {
            return $this->fail(100026);
        }

        return $this->success($this->service->refund_update((int) $id, $data['refund_price']) ? 100036 : 100037);
    }
}
