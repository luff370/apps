<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserWithdrawServices;

/**
 * Class UserWithdraw
 *
 * @package App\Http\Controllers\Admin\Finance
 */
class UserWithdrawController extends Controller
{
    /**
     * UserWithdraw constructor.
     *
     * @param UserWithdrawServices $services
     */
    public function __construct(UserWithdrawServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['status', ''],
            ['extract_type', ''],
            ['nireid', '', '', 'keyword'],
            ['data', '', '', 'time'],
        ]);

        return $this->success($this->service->getAllByPage($where));
    }

    /**
     * 提现信息详情
     *
     * @param $id
     */
    public function show($id)
    {
        $info = $this->service->get((int) $id, [], ['user']);
        if (empty($info)) {
            return $this->fail(100100);
        }

        return $this->success($info->toArray());
    }

    /**
     * 显示编辑资源表单页
     *
     * @param $id
     */
    public function edit($id)
    {
        if (!$id) {
            return $this->fail(100026);
        }

        return $this->success($this->service->edit((int) $id));
    }

    /**
     * 保存更新的资源
     *
     * @param $id
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $id = (int) $id;
        $UserExtract = $this->service->getExtract($id);
        if (!$UserExtract) {
            $this->fail(100026);
        }
        if ($UserExtract['extract_type'] == 'alipay') {
            $data = $this->getMore([
                'real_name',
                'mark',
                'extract_price',
                'alipay_code',
            ]);
            if (!$data['real_name']) {
                return $this->fail(400107);
            }
            if ($data['extract_price'] <= -1) {
                return $this->fail(400108);
            }
            if (!$data['alipay_code']) {
                return $this->fail(400109);
            }
        } else {
            if ($UserExtract['extract_type'] == 'weixin') {
                $data = $this->getMore([
                    'real_name',
                    'mark',
                    'extract_price',
                    'wechat',
                ]);
                if ($data['extract_price'] <= -1) {
                    return $this->fail(400108);
                }
                if (!$data['wechat']) {
                    return $this->fail(400110);
                }
            } else {
                $data = $this->getMore([
                    'real_name',
                    'extract_price',
                    'mark',
                    'bank_code',
                    'bank_address',
                ]);
                if (!$data['real_name']) {
                    return $this->fail(400107);
                }
                if ($data['extract_price'] <= -1) {
                    return $this->fail(400108);
                }
                if (!$data['bank_code']) {
                    return $this->fail(400111);
                }
                if (!$data['bank_address']) {
                    return $this->fail(400112);
                }
            }
        }

        return $this->success($this->service->update($id, $data) ? 100001 : 100007);
    }

    /**
     * 拒绝
     *
     * @param $id
     */
    public function refuse($id)
    {
        if (!$id) {
            $this->fail(100100);
        }

        $data = $this->getMore([
            ['message', ''],
        ]);

        if ($data['message'] == '') {
            return $this->fail(400113);
        }

        return $this->success($this->service->refuse((int) $id, $data['message'], adminInfo()) ? 100014 : 100015);
    }

    /**
     * 通过
     *
     * @param $id
     */
    public function adopt($id)
    {
        if (!$id) {
            $this->fail(100100);
        }

        return $this->success($this->service->adopt((int) $id) ? 100014 : 100015);
    }

    public function settlementForm($id)
    {
        if (!$id) {
            $this->fail(100100);
        }

        return $this->success($this->service->settlementForm($id));
    }

    public function settlement($id)
    {
        $data = $this->getMore([
            ['pay_voucher', ''],
            ['remark', ''],
        ]);
        $data['operator_name'] = adminInfo()['real_name'] ?? '';

        if ($this->service->settlement($id, $data)) {
            return $this->success('代理佣金结算成功');
        } else {
            return $this->fail('结算失败，请重新尝试');
        }
    }

    /**
     * 备注
     *
     * @param $id
     */
    public function remark($id)
    {
        if (!$id) {
            $this->fail(100100);
        }

        $data = $this->getMore([
            ['remark', ''],
        ]);

        $this->service->update($id, $data);

        return $this->success(100014);
    }
}
