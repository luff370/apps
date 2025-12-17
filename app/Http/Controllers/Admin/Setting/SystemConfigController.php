<?php

namespace App\Http\Controllers\Admin\Setting;

use Illuminate\Http\Request;
use App\Models\SystemConfig;
use App\Models\SystemConfigTab;
use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Support\Services\SystemConfigService;
use App\Services\System\Config\SystemConfigServices;
use App\Services\System\Config\SystemConfigTabServices;

/**
 * 系统配置
 * Class SystemConfig
 *
 * @package App\Http\Controllers\Admin\Setting
 */
class SystemConfigController extends Controller
{
    /**
     * SystemConfig constructor.
     *
     * @param SystemConfigServices $services
     */
    public function __construct(SystemConfigServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            // ['app_id', 0],
            ['tab_id', 0],
            // ['status', 0],
        ]);
        if (!$where['tab_id']) {
            return $this->fail(100100);
        }


        return $this->success($this->service->getConfigList($where));
    }

    /**
     * 显示创建资源表单页.
     *
     * @param $type
     */
    public function create()
    {
        [$type, $tabId] = $this->getMore([
            [['type', 'd'], ''],
            [['tab_id', 'd'], 1],
        ], true);

        return $this->success($this->service->createFormRule($type, $tabId));
    }

    /**
     * 保存新建的资源
     *
     * @throws AdminException
     */
    public function store()
    {
        $data = $this->getMore([
            ['menu_name', ''],
            ['type', ''],
            ['input_type', 'input'],
            ['config_tab_id', 0],
            ['parameter', ''],
            ['upload_type', 1],
            ['required', ''],
            ['width', 0],
            ['high', 0],
            ['value', ''],
            ['info', ''],
            ['desc', ''],
            ['sort', 0],
            ['status', 0],
        ]);
        if (!$data['info']) {
            return $this->fail(400274);
        }
        if (!$data['menu_name']) {
            return $this->fail(400275);
        }
        if (!$data['desc']) {
            return $this->fail(400276);
        }
        if ($data['sort'] < 0) {
            $data['sort'] = 0;
        }
        if ($data['type'] == 'text') {
            if (!$data['width']) {
                return $this->fail(400277);
            }
            if ($data['width'] <= 0) {
                return $this->fail(400278);
            }
        }
        if ($data['type'] == 'textarea') {
            if (!$data['width']) {
                return $this->fail(400279);
            }
            if (!$data['high']) {
                return $this->fail(400280);
            }
            if ($data['width'] < 0) {
                return $this->fail(400281);
            }
            if ($data['high'] < 0) {
                return $this->fail(400282);
            }
        }
        if ($data['type'] == 'radio' || $data['type'] == 'checkbox') {
            if (!$data['parameter']) {
                return $this->fail(400283);
            }
            $this->service->validateRadioAndCheckbox($data);
        }

        $configTabInfo = SystemConfigTab::query()->find($data['config_tab_id']);
        if (!$configTabInfo) {
            return $this->fail('配置分类不存在');
        }

        $data['app_id'] = $configTabInfo['app_id'];
        $data['value'] = json_encode($data['value']);

        $config = $this->service->getOne(['menu_name' => $data['menu_name']]);
        if ($config) {
            $this->service->update($config['id'], $data, 'id');
        } else {
            $this->service->save($data);
        }

        // 清除缓存
        SystemConfigService::cacheByAppId($data['app_id']);

        return $this->success(400284);
    }

    /**
     * 显示指定的资源
     *
     * @param int $id
     */
    public function show($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        $info = $this->service->getReadList((int) $id);

        return $this->success(compact('info'));
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     */
    public function edit($id)
    {
        return $this->success($this->service->editConfigForm((int) $id));
    }

    /**
     * 保存更新的资源
     *
     * @param int $id
     */
    public function update(Request $request, $id)
    {
        $type = $request->get('type');
        if ($type == 'text' || $type == 'textarea' || $type == 'radio' || ($type == 'upload' && ($request->get('upload_type') == 1 || $request->get('upload_type') == 3))) {
            $value = $request->get('value');
        } else {
            $value = $request->get('value/a');
        }
        if (!$value) {
            $value = $request->get('menu_name');
        }
        $data = $this->getMore([
            ['menu_name', ''],
            ['type', ''],
            ['input_type', 'input'],
            ['config_tab_id', 0],
            ['parameter', ''],
            ['upload_type', 1],
            ['required', ''],
            ['width', 0],
            ['high', 0],
            ['value', $value],
            ['info', ''],
            ['desc', ''],
            ['sort', 0],
            ['status', 0],
        ]);

        $configTabInfo = SystemConfigTab::query()->find($data['config_tab_id']);
        if (!$configTabInfo) {
            return $this->fail('配置分类不存在');
        }

        $data['app_id'] = $configTabInfo['app_id'];
        $data['value'] = json_encode($data['value']);
        $this->service->update($id, $data);

        // 清除缓存
        SystemConfigService::cacheByAppId($configTabInfo['app_id']);

        return $this->success(100001);
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     */
    public function destroy($id)
    {
        // 清除缓存
        $appId = SystemConfig::query()->where('id', $id)->value('app_id');
        SystemConfigService::cacheByAppId($appId);

        if (!$this->service->delete($id)) {
            return $this->fail(100008);
        } else {
            return $this->success(100002);
        }
    }

    /**
     * 修改状态
     *
     * @param $id
     * @param $status
     */
    public function set_status($id, $status)
    {
        if ($status == '' || $id == 0) {
            return $this->fail(100100);
        }
        $this->service->update($id, ['status' => $status]);

        // 清除缓存
        $appId = SystemConfig::query()->where('id', $id)->value('app_id');
        SystemConfigService::cacheByAppId($appId);

        return $this->success(100014);
    }

    /**
     * 基础配置
     *
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function edit_basics(Request $request)
    {
        $tabId = $request->get('tab_id');
        if (!$tabId) {
            return $this->fail(100100);
        }
        $url = request()->url();

        return $this->success($this->service->getConfigForm($url, $tabId));
    }

    /**
     * 保存数据    true
     * */
    public function save_basics(Request $request): \Illuminate\Http\JsonResponse
    {
        $post = $request->all();
        $appId = $post['app_id'];

        foreach ($post as $k => $v) {
            if (is_array($v)) {
                $res = $this->service->getUploadTypeList($k);
                foreach ($res as $kk => $vv) {
                    if ($kk == 'upload') {
                        if ($vv == 1 || $vv == 3) {
                            $post[$k] = $v[0];
                        }
                    }
                }
            }
        }
        // $this->validateWithScene($post, \App\Http\Requests\Setting\SystemConfigValidata::class);
        if (isset($post['upload_type'])) {
            $this->service->checkThumbParam($post);
        }
        if (isset($post['extract_type']) && !count($post['extract_type'])) {
            return $this->fail(400753);
        }
        if (isset($post['store_brokerage_binding_status'])) {
            $this->service->checkBrokerageBinding($post);
        }
        if (isset($post['store_brokerage_ratio']) && isset($post['store_brokerage_two'])) {
            $num = $post['store_brokerage_ratio'] + $post['store_brokerage_two'];
            if ($num > 100) {
                return $this->fail(400285);
            }
        }
        if (isset($post['spread_banner'])) {
            $num = count($post['spread_banner']);
            if ($num > 5) {
                return $this->fail(400286);
            }
        }
        if (isset($post['user_extract_min_price'])) {
            if (!preg_match('/[0-9]$/', $post['user_extract_min_price'])) {
                return $this->fail(400287);
            }
        }
        if (isset($post['wss_open'])) {
            $this->service->saveSslFilePath((int) $post['wss_open'], $post['wss_local_pk'] ?? '', $post['wss_local_cert'] ?? '');
        }
        if (isset($post['store_brokerage_price']) && $post['store_brokerage_statu'] == 3) {
            if ($post['store_brokerage_price'] === '') {
                return $this->fail(400288);
            }
            if ($post['store_brokerage_price'] < 0) {
                return $this->fail(400289);
            }
        }
        if (isset($post['store_brokerage_binding_time']) && $post['store_brokerage_binding_status'] == 2) {
            if (!preg_match("/^[0-9][0-9]*$/", $post['store_brokerage_binding_time'])) {
                return $this->fail(400290);
            }
        }
        if (isset($post['uni_brokerage_price']) && $post['uni_brokerage_price'] < 0) {
            return $this->fail(400756);
        }
        if (isset($post['day_brokerage_price_upper']) && $post['day_brokerage_price_upper'] < -1) {
            return $this->fail(400757);
        }
        if (isset($post['pay_new_weixin_open']) && (bool) $post['pay_new_weixin_open']) {
            if (empty($post['pay_new_weixin_mchid'])) {
                return $this->fail(400763);
            }
        }

        if (isset($post['weixin_ckeck_file'])) {
            $from = public_path() . $post['weixin_ckeck_file'];
            $to = public_path() . array_reverse(explode('/', $post['weixin_ckeck_file']))[0];
            @copy($from, $to);
        }
        if (isset($post['ico_path'])) {
            $from = public_path() . $post['ico_path'];
            $toAdmin = public_path('admin') . 'favicon.ico';
            $toHome = public_path('home') . 'favicon.ico';
            $toPublic = public_path() . 'favicon.ico';
            @copy($from, $toAdmin);
            @copy($from, $toHome);
            @copy($from, $toPublic);
        }
        if (isset($post['reward_integral']) || isset($post['reward_money'])) {
            if ($post['reward_integral'] < 0 || $post['reward_money'] < 0) {
                return $this->fail(400558);
            }
        }
        foreach ($post as $k => $v) {
            $config_one = $this->service->getOne(['app_id' => $appId, 'menu_name' => $k]);
            if ($config_one) {
                $config_one['value'] = $v ?? '';
                $this->service->valiDateValue($config_one);
                $config_one->save();
            }
        }

        // 清除缓存
        SystemConfigService::cacheByAppId($appId);

        return $this->success(100001);
    }

    /**
     * 获取系统设置头部分类
     *
     * @param SystemConfigTabServices $services
     */
    public function header_basics(SystemConfigTabServices $services)
    {
        [$type, $pid, $appId] = $this->getMore([
            ['type', 0],
            ['pid', 0],
            ['app_id', 0],
        ], true);

        if ($type == 3) {//其它分类
            $config_tab = [];
        } else {
            $config_tab = $services->getConfigTab($pid, $type, $appId);
        }

        return $this->success(compact('config_tab'));
    }

    /**
     * 获取单个配置的值
     *
     * @param $name
     */
    public function get_system($name)
    {
        $value = sys_config($name);

        return $this->success(compact('value'));
    }

    /**
     * 获取某个分类下的所有配置
     *
     * @param $tabId
     */
    public function get_config_list($tabId)
    {
        $list = $this->service->getConfigTabAllList($tabId);
        $data = [];
        foreach ($list as $item) {
            $data[$item['menu_name']] = json_decode($item['value']);
        }

        return $this->success($data);
    }

    /**
     * 获取版本号信息
     */
    public function getVersion()
    {
        $version = get_admin_version();

        return $this->success([
            'version' => $version,
            'label' => 19,
        ]);
    }
}
