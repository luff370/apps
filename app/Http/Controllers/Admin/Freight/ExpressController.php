<?php

namespace App\Http\Controllers\Admin\Freight;

use App\Http\Controllers\Admin\Controller;
use App\Services\Shipping\ExpressServices;

/**
 * 物流
 * Class Express
 *
 * @package App\Http\Controllers\Admin\Freight
 */
class ExpressController extends Controller
{
    /**
     * 构造方法
     * Express constructor.
     *
     * @param ExpressServices $services
     */
    public function __construct(ExpressServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取物流列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['keyword', ''],
        ]);

        return $this->success($this->service->getExpressList($where));
    }

    /**
     * 显示创建资源表单页
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function create()
    {
        return $this->success($this->service->createForm());
    }

    /**
     * 保存新建的资源
     */
    public function save()
    {
        $data = $this->getMore([
            'name',
            'code',
            ['sort', 0],
            ['is_show', 0],
        ]);
        if (!$data['name']) {
            return $this->fail(400400);
        }
        $this->service->save($data);

        return $this->success(400401);
    }

    /**
     * 显示编辑资源表单页
     *
     * @param $id
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function edit($id)
    {
        return $this->success($this->service->updateForm((int) $id));
    }

    /**
     * 保存更新的资源
     *
     * @param $id
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['account', ''],
            ['key', ''],
            ['net_name', ''],
            ['courier_name', ''],
            ['customer_name', ''],
            ['code_name', ''],
            ['sort', 0],
            ['is_show', 0],
        ]);
        if (!$expressInfo = $this->service->get($id)) {
            return $this->fail(100026);
        }
        if ($expressInfo['partner_id'] == 1 && !$data['account']) {
            return $this->fail(400402);
        }
        if ($expressInfo['partner_key'] == 1 && !$data['key']) {
            return $this->fail(400403);
        }
        if ($expressInfo['net'] == 1 && !$data['net_name']) {
            return $this->fail(400404);
        }
        if ($expressInfo['check_man'] == 1 && !$data['courier_name']) {
            return $this->fail(500001);
        }
        if ($expressInfo['partner_name'] == 1 && !$data['customer_name']) {
            return $this->fail(500002);
        }
        if ($expressInfo['is_code'] == 1 && !$data['code_name']) {
            return $this->fail(500003);
        }
        $expressInfo->account = $data['account'];
        $expressInfo->key = $data['key'];
        $expressInfo->net_name = $data['net_name'];
        $expressInfo->courier_name = $data['courier_name'];
        $expressInfo->customer_name = $data['customer_name'];
        $expressInfo->code_name = $data['code_name'];
        $expressInfo->sort = $data['sort'];
        $expressInfo->is_show = $data['is_show'];
        $expressInfo->status = 1;
        $expressInfo->save();

        return $this->success(100001);
    }

    /**
     * 删除指定资源
     *
     * @param $id
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $res = $this->service->delete($id);
        if (!$res) {
            return $this->fail(100008);
        } else {
            return $this->success(100002);
        }
    }

    /**
     * 修改状态
     *
     * @param int $id
     * @param string $status
     */
    public function set_status($id = 0, $status = '')
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['is_show' => $status]);

        return $this->success(100014);
    }

    /**
     * 同步平台快递公司
     */
    public function syncExpress()
    {
        $this->service->syncExpress();

        return $this->success(100039);
    }
}
