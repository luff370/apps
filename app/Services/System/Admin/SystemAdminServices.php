<?php

namespace App\Services\System\Admin;

use App\Services\Service;
use App\Support\Utils\JwtAuth;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use App\Dao\System\Admin\SystemAdminDao;
use App\Services\User\UserExtractServices;
use App\Services\System\SystemMenuServices;

/**
 * 管理员service
 * Class SystemAdminServices
 *
 * @package App\Services\System\admin
 * @method getAdminIds(int $level) 根据管理员等级获取管理员id
 * @method getOrdAdmin(string $field, int $level) 获取低于等级的管理员名称和id
 */
class SystemAdminServices extends Service
{
    const MANAGER_TYPE_ROLE = 2; //运营管理角色管理员组

    /**
     * form表单创建
     *
     * @var FormBuilder
     */
    protected $builder;

    /**
     * SystemAdminServices constructor.
     *
     * @param SystemAdminDao $dao
     */
    public function __construct(SystemAdminDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * 管理员登陆
     *
     * @param string $account
     * @param string $password
     *
     * @throws \App\Exceptions\AdminException
     */
    public function verifyLogin(string $account, string $password)
    {
        $adminInfo = $this->dao->accountByAdmin($account);
        if (!$adminInfo || !password_verify($password, $adminInfo->pwd)) {
            return false;
        }
        if (!$adminInfo->status) {
            throw new AdminException(400595);
        }
        $adminInfo->last_time = time();
        $adminInfo->last_ip = app('request')->ip();
        $adminInfo->login_count++;
        $adminInfo->save();

        return $adminInfo;
    }

    /**
     * 文件管理员登陆
     *
     * @param string $account
     * @param string $password
     *
     * @throws \App\Exceptions\AdminException
     */
    public function verifyFileLogin(string $account, string $password)
    {
        $adminInfo = $this->dao->accountByAdmin($account);
        if (!$adminInfo) {
            throw new AdminException(400594);
        }
        if (!$adminInfo->status) {
            throw new AdminException(400595);
        }
        if (!password_verify($password, $adminInfo->file_pwd)) {
            throw new AdminException(400140);
        }
        $adminInfo->last_time = time();
        $adminInfo->last_ip = app('request')->ip();
        $adminInfo->login_count++;
        $adminInfo->save();

        return $adminInfo;
    }

    /**
     * 后台登陆获取菜单获取token
     *
     * @param string $account
     * @param string $password
     * @param string $type
     * @param string $key
     *
     * @return array|bool
     * @throws \App\Exceptions\AdminException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function login(string $account, string $password, string $type, string $key = ''): bool|array
    {
        $adminInfo = $this->verifyLogin($account, $password);
        if (!$adminInfo) {
            return false;
        }

        $this->dao->delCacheById($adminInfo['id']);

        $tokenInfo = JwtAuth::createToken($adminInfo->id, $type, ['pwd' => md5($adminInfo->pwd)]);

        /** @var SystemMenuServices $services */
        $services = app(SystemMenuServices::class);
        [$menus, $uniqueAuth] = $services->getMenusList($adminInfo->roles, (int) $adminInfo['level']);

        return [
            'token' => $tokenInfo['token'],
            'expires_time' => $tokenInfo['params']['exp'],
            'menus' => $menus,
            'unique_auth' => $uniqueAuth,
            'user_info' => [
                'id' => $adminInfo->getData('id'),
                'account' => $adminInfo->getData('account'),
                'head_pic' => $adminInfo->getData('head_pic'),
                'level' => $adminInfo->getData('level'),
            ],
            'logo' => sys_config('site_logo'),
            'logo_square' => sys_config('site_logo_square'),
            'version' => get_admin_version(),
            'newOrderAudioLink' => get_file_link(sys_config('new_order_audio_link')),
            'queue' => $queue ?? true,
            'timer' => $timer ?? true,
        ];
    }

    /**
     * 获取登陆前的login等信息
     *
     * @return array
     */
    public function getLoginInfo()
    {
        $key = uniqid();
        $data = [
            'slide' => sys_data('admin_login_slide') ?? [],
            'logo_square' => sys_config('site_logo_square'),//透明
            'logo_rectangle' => sys_config('site_logo'),    //方形
            'login_logo' => sys_config('login_logo'),       //登陆
            'site_name' => sys_config('site_name'),
            'copyright' => sys_config('copyright'),
            'version' => 'v1.0',
            'key' => $key,
            'login_captcha' => 0,
        ];
        if (sys_config('login_captcha') > 1) {
            $data['login_captcha'] = 1;
        }

        return $data;
    }

    /**
     * 获取表单所需的管理员名称列表
     *
     * @param int $roleId
     *
     * @return array
     */
    public function getAdminFormSelect(int $roleId, $label = 'account')
    {
        $list = $this->getColumn(['roles' => $roleId, 'status' => 1], $label, 'id');
        $options = [];
        foreach ($list as $id => $labelName) {
            $options[] = ['label' => $labelName, 'value' => $id];
        }

        return $options;
    }

    /**
     * 管理员列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getAdminList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList($where, $page, $limit);
        $count = $this->dao->count($where);

        /** @var SystemRoleServices $service */
        $service = app(SystemRoleServices::class);
        $allRole = $service->getRoleArray();
        foreach ($list as &$item) {
            if ($item['roles']) {
                $roles = [];
                foreach ($item['roles'] as $id) {
                    if (isset($allRole[$id])) {
                        $roles[] = $allRole[$id];
                    }
                }
                if ($roles) {
                    $item['roles'] = implode(',', $roles);
                } else {
                    $item['roles'] = '';
                }
            }
            $item['_last_time'] = $item['last_time'] ? date('Y-m-d H:i:s', $item['last_time']) : '';
        }

        return compact('list', 'count');
    }

    /**
     * 创建管理员表单
     *
     * @param int $level
     * @param array $formData
     *
     * @return mixed
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createAdminForm(int $level, array $formData = [])
    {
        $f[] = $this->builder->input('account', '管理员账号', $formData['account'] ?? '')->required('请填写管理员账号');

        if (empty($formData)) {
            $f[] = $this->builder->input('pwd', '管理员密码')->type('password')->required('请填写管理员密码');
            $f[] = $this->builder->input('conf_pwd', '确认密码')->type('password')->required('请输入确认密码');
        } else {
            $f[] = $this->builder->input('pwd', '管理员密码')->type('password');
            $f[] = $this->builder->input('conf_pwd', '确认密码')->type('password');
        }

        $f[] = $this->builder->input('real_name', '管理员姓名', $formData['real_name'] ?? '')->required('请输入管理员姓名');

        /** @var SystemRoleServices $service */
        $service = app(SystemRoleServices::class);
        $options = $service->getRoleFormSelect($level);
        if (isset($formData['roles'])) {
            foreach ($formData['roles'] as &$item) {
                $item = intval($item);
            }
        }
        $f[] = $this->builder->select('roles', '管理员身份', $formData['roles'] ?? 0)->setOptions(FormBuilder::setOptions($options))->multiple(false);
        $f[] = $this->builder->radio('status', '状态', $formData['status'] ?? 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);

        return $f;
    }

    /**
     * 添加管理员form表单获取
     *
     * @param int $level
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm(int $level)
    {
        return create_form('管理员添加', $this->createAdminForm($level), url('/admin/setting/admin'));
    }

    /**
     * 创建管理员
     *
     * @param array $data
     *
     * @return
     */
    public function create(array $data)
    {
        if (empty($data['roles'])) {
            throw new AdminException('请选择管理员身份');
        }

        if ($data['conf_pwd'] != $data['pwd']) {
            throw new AdminException(400264);
        }
        unset($data['conf_pwd']);

        if ($this->dao->count(['account' => $data['account'], 'is_del' => 0])) {
            throw new AdminException(400596);
        }

        $data['pwd'] = $this->passwordHash($data['pwd']);
        $data['add_time'] = time();
        // $data['roles'] = implode(',', $data['roles']);
        $data['account_type'] = $data['roles'];

        return \DB::transaction(function () use ($data) {
            if ($res = $this->dao->save($data)) {
                return $res;
            } else {
                throw new AdminException(100022);
            }
        });
    }

    /**
     * 修改管理员表单
     *
     * @param int $level
     * @param int $id
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function updateForm(int $level, int $id)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo) {
            throw new AdminException(400594);
        }
        if ($adminInfo->is_del) {
            throw new AdminException(400452);
        }

        return create_form('管理员修改', $this->createAdminForm($level, $adminInfo->toArray()), url('/admin/setting/admin/' . $id), 'PUT');
    }

    /**
     * 修改管理员
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function save(int $id, array $data)
    {
        if (!$adminInfo = $this->dao->get($id)) {
            throw new AdminException(400594);
        }
        if ($adminInfo->is_del) {
            throw new AdminException(400452);
        }

        //修改密码
        if ($data['pwd']) {
            if (!$data['conf_pwd']) {
                throw new AdminException(400263);
            }

            if ($data['conf_pwd'] != $data['pwd']) {
                throw new AdminException(400264);
            }
            $adminInfo->pwd = $this->passwordHash($data['pwd']);
        }
        //修改账号
        if (isset($data['account']) && $data['account'] != $adminInfo->account && $this->dao->isAccountUsable($data['account'], $id)) {
            throw new AdminException(400596);
        }

        if (isset($data['roles'])) {
            // $adminInfo->roles = implode(',', $data['roles']);
            $adminInfo->roles = $data['roles'];
            $adminInfo->account_type = $data['roles'];
        }
        $adminInfo->real_name = $data['real_name'] ?? $adminInfo->real_name;
        $adminInfo->account = $data['account'] ?? $adminInfo->account;
        $adminInfo->status = $data['status'] ?? $adminInfo->status;
        if ($adminInfo->save()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 修改当前管理员信息
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function updateAdmin(int $id, array $data)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo) {
            throw new AdminException(400451);
        }
        if ($adminInfo->is_del) {
            throw new AdminException(400452);
        }
        if (!$data['real_name']) {
            throw new AdminException(400453);
        }
        if ($data['pwd']) {
            if (!password_verify($data['pwd'], $adminInfo['pwd'])) {
                throw new AdminException(400597);
            }
            if (!$data['new_pwd']) {
                throw new AdminException(400598);
            }
            if (!$data['conf_pwd']) {
                throw new AdminException(400263);
            }
            if ($data['new_pwd'] != $data['conf_pwd']) {
                throw new AdminException(400264);
            }
            $adminInfo->pwd = $this->passwordHash($data['new_pwd']);
        }

        $adminInfo->real_name = $data['real_name'];
        $adminInfo->head_pic = $data['head_pic'];
        if ($adminInfo->save()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 设置当前管理员文件管理密码
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function setFilePassword(int $id, array $data)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo) {
            throw new AdminException(400451);
        }
        if ($adminInfo->is_del) {
            throw new AdminException(400452);
        }
        if ($data['file_pwd']) {
            if ($adminInfo->level != 0) {
                throw new AdminException(400611);
            }
            if (!$data['conf_file_pwd']) {
                throw new AdminException(400263);
            }
            if ($data['file_pwd'] != $data['conf_file_pwd']) {
                throw new AdminException(400264);
            }
            $adminInfo->file_pwd = $this->passwordHash($data['file_pwd']);
        }
        if ($adminInfo->save()) {
            return true;
        } else {
            return false;
        }
    }

    /** 后台订单下单，评论，支付成功，后台消息提醒
     *
     * @param $event
     */
    public function adminNewPush()
    {
        try {
            /** @var StoreOrderServices $orderServices */
            $orderServices = app(StoreOrderServices::class);
            $data['ordernum'] = $orderServices->count(['is_del' => 0, 'status' => 1, 'shipping_type' => 1]);
            /** @var StoreProductServices $productServices */
            $productServices = app(StoreProductServices::class);
            $data['inventory'] = $productServices->count(['type' => 5]);
            /** @var StoreProductReplyServices $replyServices */
            $replyServices = app(StoreProductReplyServices::class);
            $data['commentnum'] = $replyServices->count(['is_reply' => 0]);
            /** @var UserExtractServices $extractServices */
            $extractServices = app(UserExtractServices::class);
            $data['reflectnum'] = $extractServices->getCount(['status' => 0]);//提现
            $data['msgcount'] = intval($data['ordernum']) + intval($data['inventory']) + intval($data['commentnum']) + intval($data['reflectnum']);
            ChannelServices::instance()->send('ADMIN_NEW_PUSH', $data);
        } catch (\Exception $e) {
        }
    }
}
