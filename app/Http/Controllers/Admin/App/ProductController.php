<?php

namespace App\Http\Controllers\Admin\App;

use App\Dao\App\ProductDao;
use App\Models\MemberProduct;
use Illuminate\Http\Request;
use App\Services\App\ProductService;
use App\Http\Controllers\Admin\Controller;

/**
 * 产品管理
 */
class ProductController extends Controller
{
    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取产品列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['app_id', ''],
            ['lang', ''],
            ['status', ''],
            ['keyword', ''],
        ]);
        $data = $this->service->getAllByPage($where, ['*'], ['sort' => 'desc'], ['app']);

        return $this->success($data);
    }

    /**
     * 保存新建产品
     */
    public function store(Request $request)
    {
        $data = $this->getMore([
                ['id', ''],
                ['app_id', ''],
                ['name', ''],
                ['label', ''],
                ['keyword', ''],
                ['ot_price', ''],
                ['price', ''],
                ['lang', ''],
                ['validity_type', ''],
                ['give_type', ''],
                ['validity', ''],
                ['give_validity', ''],
                ['pay_product_id', ''],
                ['filter_code', ''],
                ['platform', ''],
                ['serial_number', ''],
                ['is_subscribe', ''],
                ['pay_cycle', ''],
                ['pay_cycle_val', ''],
                ['grace_period_type', ''],
                ['grace_period', ''],
                ['renewal_price', ''],
                ['is_enable', ''],
                ['remark', ''],
                ['buy_info', ''],
            ]);

        logger()->info('---product---', $data);

        $this->service->save($data);

        return $this->success('保存成功');
    }

    public function show($id)
    {
        $data = $this->service->getRow($id);

        return $this->success($data);
    }

    /**
     * 删除产品
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 修改产品排序
     */
    public function setSort($id, $sort)
    {
        if ($sort == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['sort' => $sort]);

        return $this->success(100014);
    }

    public function copy($id)
    {
        $info = $this->service->get($id);
        if (empty($info)) {
            return $this->fail(100100);
        }

        MemberProduct::query()->create(
            [
                'app_id' => $info['app_id'],
                'name'=>$info['name'],
                'label' => $info['label'],
                'keyword' => $info['keyword'],
                'ot_price' => $info['ot_price'],
                'price' => $info['price'],
                'validity_type' => $info['validity_type'],
                'give_type' => $info['give_type'],
                'validity' => $info['validity'],
                'give_validity' => $info['give_validity'],
                'pay_product_id' => $info['pay_product_id'],
                'filter_code' => $info['filter_code'],
                'platform' => $info['platform'],
                'serial_number' => $info['serial_number'],
                'is_subscribe' => $info['is_subscribe'],
                'pay_cycle' => $info['pay_cycle'],
                'pay_cycle_val' => $info['pay_cycle_val'],
                'grace_period_type' => $info['grace_period_type'],
                'grace_period' => $info['grace_period'],
                'renewal_price' => $info['renewal_price'],
                'is_enable' => $info['is_enable'],
                'remark' => $info['remark'],
                'buy_info' => $info['buy_info'],

            ]
        );

        return $this->success(100021);
    }
}
