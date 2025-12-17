<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\Controller;
use app\services\out\OutAccountServices;
use app\services\out\OutInterfaceServices;
use app\outapi\validate\StoreOutAccountValidate;

/**
 * 对外接口账户
 * Class SystemOutAccount
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemOutAccountController extends Controller
{
    /**
     * 构造方法
     * SystemOut constructor.
     *
     * @param OutAccountServices $services
     */
    public function __construct(OutAccountServices $services)
    {
        $this->service = $services;
    }

    /**
     * 账号信息
     *
     * @return string
     * @throws \Exception
     */
    public function index()
    {
        $where = $this->getMore([
            ['name', '', ''],
            ['status', ''],
        ]);

        return $this->success($this->service->getList($where));
    }

    /**
     * 修改状态
     *
     * @param string $status
     * @param string $id
     */
    public function set_status($id = '', $status = '')
    {
        if ($status == '' || $id == '') {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        return $this->success($status == 1 ? 100012 : 100013);
    }

    /**
     * 删除
     *
     * @param $id
     */
    public function delete($id)
    {
        if ($id == '') {
            return $this->fail(100100);
        }
        $this->service->update($id, ['is_del' => 1]);

        return $this->success(100002);
    }

    /**
     * 保存
     */
    public function save()
    {
        $data = $this->getMore([
            [['appid', 's'], ''],
            [['appsecret', 's'], ''],
            [['title', 's'], ''],
            ['rules', []],
        ]);
        $this->validateWithScene($data, StoreOutAccountValidate::class, 'save');
        if ($this->service->getOne(['appid' => $data['appid']])) {
            return $this->fail('账号重复');
        }
        if (!$data['appsecret']) {
            unset($data['appsecret']);
        } else {
            $data['appsecret'] = password_hash($data['appsecret'], PASSWORD_DEFAULT);
        }
        $data['add_time'] = time();
        $data['rules'] = implode(',', $data['rules']);
        if (!$this->service->save($data)) {
            return $this->fail(100006);
        } else {
            return $this->success(100000);
        }
    }

    /**
     * 修改
     *
     * @param string $id
     */
    public function update($id = '')
    {
        $data = $this->getMore([
            [['appsecret', 's'], ''],
            [['title', 's'], ''],
            ['rules', []],
        ]);

        $this->validateWithScene($data, StoreOutAccountValidate::class, 'update');
        if (!$data['appsecret']) {
            unset($data['appsecret']);
        } else {
            $data['appsecret'] = password_hash($data['appsecret'], PASSWORD_DEFAULT);
        }
        if (!$this->service->getOne(['id' => $id])) {
            return $this->fail('没有此账号');
        }
        $data['rules'] = implode(',', $data['rules']);
        $res = $this->service->update($id, $data);
        if (!$res) {
            return $this->fail(100006);
        } else {
            return $this->success(100000);
        }
    }

    /**
     * 设置账号推送接口
     *
     * @param $id
     */
    public function outSetUpSave($id)
    {
        $data = $this->getMore([
            ['push_open', 0],
            ['push_account', ''],
            ['push_password', ''],
            ['push_token_url', ''],
            ['user_update_push', ''],
            ['order_create_push', ''],
            ['order_pay_push', ''],
            ['refund_create_push', ''],
            ['refund_cancel_push', ''],
        ]);
        $this->service->outSetUpSave($id, $data);

        return $this->success(100000);
    }

    /**
     * 对外接口列表
     *
     * @param OutInterfaceServices $service
     */
    public function outInterfaceList(OutInterfaceServices $service)
    {
        return $this->success($service->outInterfaceList());
    }

    /**
     * 保存接口文档
     *
     * @param $id
     * @param OutInterfaceServices $service
     */
    public function saveInterface($id, OutInterfaceServices $service)
    {
        $data = $this->getMore([
            ['pid', 0],              //上级id
            ['type', 0],             //类型 0菜单 1接口
            ['name', ''],            //名称
            ['describe', ''],        //说明
            ['method', ''],          //方法
            ['url', ''],             //链接地址
            ['request_params', []],  //请求参数
            ['return_params', []],   //返回参数
            ['request_example', ''], //请求示例
            ['return_example', ''],  //返回示例
            ['error_code', []]       //错误码
        ]);
        $service->saveInterface((int) $id, $data);

        return $this->success(100000);
    }

    /**
     * 对外接口文档
     *
     * @param $id
     * @param OutInterfaceServices $service
     */
    public function interfaceInfo($id, OutInterfaceServices $service)
    {
        return $this->success($service->interfaceInfo($id));
    }

    /**
     * 修改接口名称
     *
     * @param OutInterfaceServices $service
     */
    public function editInterfaceName(OutInterfaceServices $service)
    {
        $data = $this->getMore([
            ['id', 0],    //上级id
            ['name', ''], //名称
        ]);
        if (!$data['id'] || !$data['name']) {
            return $this->success(100100);
        }
        $service->editInterfaceName($data);

        return $this->success(100001);
    }

    /**
     * 删除接口
     *
     * @param $id
     * @param OutInterfaceServices $service
     */
    public function delInterface($id, OutInterfaceServices $service)
    {
        if (!$id) {
            return $this->success(100100);
        }
        $service->delInterface($id);

        return $this->success(100002);
    }

    /**
     * 测试获取token接口
     */
    public function textOutUrl()
    {
        $data = $this->getMore([
            ['push_account', 0],
            ['push_password', 0],
            ['push_token_url', ''],
        ]);

        return $this->success('100014', $this->service->textOutUrl($data));
    }
}
