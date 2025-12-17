<?php

namespace App\Services\User;

use Carbon\Carbon;
use App\Models\User;
use App\Services\Service;
use App\Dao\User\UserDao;
use App\Models\SystemApp;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;
use App\Services\System\SystemUserLevelServices;

/**
 *
 * Class UserServices
 *
 * @package App\Services\User
 */
class UserServices extends Service
{
    /**
     * UserServices constructor.
     *
     * @param UserDao $dao
     */
    public function __construct(UserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(int $uid, $field = '*')
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }

        return $this->dao->get($uid, $field);
    }

    /**
     * 获取用户列表
     *
     * @param array $where
     * @param string $field
     *
     * @return array
     */
    public function getUserList(array $where, string $field = '*'): array
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList($where, $field, $page, $limit);
        $count = $this->getCount($where);

        return [$list, $count];
    }

    /**
     * 列表条数
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where, bool $is_list = false)
    {
        return $this->dao->getCount($where);
    }

    /**
     * 保存用户信息
     *
     * @param $user
     * @param int $spreadUid
     * @param string $userType
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \App\Exceptions\AdminException
     */
    public function setUserInfo($user, int $spreadUid = 0, string $userType = 'wechat'): \Illuminate\Database\Eloquent\Model
    {
        $data = [
            'account' => $user['account'] ?? 'wx' . rand(1, 9999) . time(),
            'pwd' => $user['pwd'] ?? md5('123456'),
            'nickname' => $user['nickname'] ?? '',
            'avatar' => $user['headimgurl'] ?? '',
            'phone' => $user['phone'] ?? '',
            'add_time' => time(),
            'add_ip' => app()->request->ip(),
            'last_time' => time(),
            'last_ip' => app()->request->ip(),
            'user_type' => $userType,
            'staff_id' => $user['staff_id'] ?? 0,
            'agent_id' => $user['agent_id'] ?? 0,
            'division_id' => $user['division_id'] ?? 0,
        ];
        if ($spreadUid) {
            $data['spread_uid'] = $spreadUid;
            $data['spread_time'] = time();
        }
        $res = $this->dao->save($data);
        if (!$res) {
            throw new AdminException(400684);
        }


        return $res;
    }

    /**
     * 根据条件获取用户指定字段列表
     *
     * @param array $where
     * @param string $field
     * @param string $key
     *
     * @return array
     */
    public function getColumn(array $where, string $field = '*', string $key = '')
    {
        return $this->dao->getColumn($where, $field, $key);
    }




    /**
     * 获取分销用户
     *
     * @param array $where
     * @param string $field
     *
     * @return array
     */
    public function getAgentUserList(array $where = [], string $field = '*', $is_page = true)
    {
        $where_data['status'] = 1;
        $where_data['is_promoter'] = 1;
        $where_data['spread_open'] = 1;
        // 人人分销时  去除分销员字段的限制
        $store_brokerage_statu = sys_config('store_brokerage_statu');
        if ($store_brokerage_statu == 2) {
            unset($where_data['is_promoter']);
        }
        if (isset($where['nickname']) && $where['nickname'] !== '') {
            $where_data['like'] = $where['nickname'];
        }
        if (isset($where['data']) && $where['data']) {
            $where_data['time'] = $where['data'];
        }
        [$page, $limit] = $this->getPageValue($is_page);
        $list = $this->dao->getAgentUserList($where_data, $field, $page, $limit);
        $count = $this->dao->count($where_data);

        return compact('count', 'list');
    }

    /**
     * 获取分销员ids
     *
     * @param array $where
     *
     * @return array
     */
    public function getAgentUserIds(array $where)
    {
        $where['status'] = 1;
        if (sys_config('store_brokerage_statu') != 2) {
            $where['is_promoter'] = 1;
        }
        $where['spread_open'] = 1;
        if (isset($where['nickname']) && $where['nickname'] !== '') {
            $where['like'] = $where['nickname'];
        }
        if (isset($where['data']) && $where['data']) {
            $where['time'] = $where['data'];
        }

        return $this->dao->getAgentUserIds($where);
    }

    /**
     * 获取推广人列表
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     */
    public function getSairList(array $where, string $field = '*')
    {
        $where_data = [];
        if (isset($where['id'])) {
            if (isset($where['type'])) {
                $type = (int) $where['type'];
                $type = in_array($type, [1, 2]) ? $type : 0;
                $uids = $this->getUserSpredadUids((int) $where['id'], $type);
                $where_data['id'] = count($uids) > 0 ? $uids : 0;
            }
            if (isset($where['data']) && $where['data']) {
                $where_data['time'] = $where['data'];
            }
            if (isset($where['nickname']) && $where['nickname']) {
                $where_data['like'] = $where['nickname'];
            }
            $where_data['status'] = 1;
        }
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getSairList($where_data, '*', $page, $limit);
        $count = $this->dao->count($where_data);

        return compact('list', 'count');
    }

    /**
     * 获取推广人统计
     *
     * @param array $where
     * @param string $field
     * @param int $page
     * @param int $limit
     */
    public function getSairCount(array $where)
    {
        $where_data = [];
        if (isset($where['id'])) {
            if (isset($where['type'])) {
                $uids = $this->getColumn(['spread_uid' => $where['id']], 'id');
                switch ((int) $where['type']) {
                    case 1:
                        $where_data['id'] = count($uids) > 0 ? $uids : 0;
                        break;
                    case 2:
                        if (count($uids)) {
                            $spread_uid_two = $this->dao->getColumn([['spread_uid', 'IN', $uids]], 'id');
                        } else {
                            $spread_uid_two = [];
                        }
                        $where_data['id'] = count($spread_uid_two) > 0 ? $spread_uid_two : 0;
                        break;
                    default:
                        if (count($uids)) {
                            if ($spread_uid_two = $this->dao->getColumn([['spread_uid', 'IN', $uids]], 'id')) {
                                $uids = array_merge($uids, $spread_uid_two);
                                $uids = array_unique($uids);
                                $uids = array_merge($uids);
                            }
                        }
                        $where_data['id'] = count($uids) > 0 ? $uids : 0;
                        break;
                }
            }
            if (isset($where['data']) && $where['data']) {
                $where_data['time'] = $where['data'];
            }
            if (isset($where['nickname']) && $where['nickname']) {
                $where_data['like'] = $where['nickname'];
            }
            $where_data['status'] = 1;
        }

        return $this->dao->count($where_data);
    }

    /**
     * 写入用户信息
     *
     * @param array $data
     *
     * @return bool
     */
    public function create(array $data)
    {
        if (!$this->dao->save($data)) {
            throw new AdminException(100000);
        }

        return true;
    }

    /**
     * 重置密码
     *
     * @param $id
     * @param string $password
     *
     * @return mixed
     */
    public function resetPwd(int $uid, string $password)
    {
        if (!$this->dao->update($uid, ['pwd' => $password])) {
            throw new AdminException(400685);
        }

        return true;
    }

    /**
     * 增加推广人数
     *
     * @param int $uid
     * @param int $num
     *
     * @return bool
     * @throws Exception
     */
    public function incSpreadCount(int $uid, int $num = 1)
    {
        if (!$this->dao->incField($uid, 'spread_count', $num)) {
            throw new AdminException(400686);
        }

        return true;
    }

    /**
     * 设置用户登录类型
     *
     * @param int $uid
     * @param string $type
     *
     * @return bool
     * @throws Exception
     */
    public function setLoginType(int $uid, string $type = 'h5')
    {
        if (!$this->dao->update($uid, ['login_way' => $type])) {
            throw new AdminException(400687);
        }

        return true;
    }

    /**
     * 设置推广员
     *
     * @param int $uid
     * @param int $is_promoter
     *
     * @return bool
     * @throws Exception
     */
    public function setIsPromoter(int $uid, $is_promoter = 1)
    {
        if (!$this->dao->update($uid, ['is_promoter' => $is_promoter])) {
            throw new AdminException(400688);
        }

        return true;
    }

    /**
     * 设置用户分组
     *
     * @param $uids
     * @param int $group_id
     */
    public function setUsergroupBy($uids, int $group_id)
    {
        return $this->dao->batchUpdate($uids, ['group_id' => $group_id], 'id');
    }

    /**
     * 增加用户余额
     *
     * @param int $uid
     * @param float $old_now_money
     * @param float $now_money
     *
     * @return bool
     */
    public function addNowMoney(int $uid, $old_now_money, $now_money)
    {
        if (!$this->dao->update($uid, ['now_money' => bcadd($old_now_money, $now_money, 2)])) {
            throw new AdminException(400689);
        }

        return true;
    }

    /**
     * 减少用户余额
     *
     * @param int $uid
     * @param float $old_now_money
     * @param float $now_money
     *
     * @return bool
     */
    public function cutNowMoney(int $uid, $old_now_money, $now_money)
    {
        if ($old_now_money > $now_money) {
            $money = ['now_money' => bcsub($old_now_money, $now_money, 2)];
        } else {
            $money = ['now_money' => 0];
        }
        if (!$this->dao->update($uid, $money, 'id')) {
            throw new AdminException(400690);
        }

        return true;
    }

    /**
     * 减少用户佣金
     *
     * @param int $uid
     * @param float $brokerage_price
     * @param float $price
     *
     * @return bool
     */
    public function cutBrokeragePrice(int $uid, $brokerage_price, $price)
    {
        if (!$this->dao->update($uid, ['brokerage_price' => bcsub($brokerage_price, $price, 2)])) {
            throw new AdminException(400691);
        }

        return true;
    }

    /**
     * 增加用户积分
     *
     * @param int $uid
     * @param float $old_integral
     * @param float $integral
     *
     * @return bool
     */
    public function addIntegral(int $uid, $old_integral, $integral)
    {
        if (!$this->dao->update($uid, ['integral' => bcadd($old_integral, $integral, 2)])) {
            throw new AdminException(400692);
        }

        return true;
    }

    /**
     * 减少用户积分
     *
     * @param int $uid
     * @param float $old_integral
     * @param float $integral
     *
     * @return bool
     */
    public function cutIntegral(int $uid, $old_integral, $integral)
    {
        if (!$this->dao->update($uid, ['integral' => bcsub($old_integral, $integral, 2)])) {
            throw new AdminException(400693);
        }

        return true;
    }

    /**
     * 增加用户经验
     *
     * @param int $uid
     * @param float $old_exp
     * @param float $exp
     *
     * @return bool
     */
    public function addExp(int $uid, float $old_exp, float $exp)
    {
        if (!$this->dao->update($uid, ['exp' => bcadd($old_exp, $exp, 2)])) {
            throw new AdminException(400694);
        }

        return true;
    }

    /**
     * 减少用户经验
     *
     * @param int $uid
     * @param float $old_exp
     * @param float $exp
     *
     * @return bool
     */
    public function cutExp(int $uid, float $old_exp, float $exp)
    {
        if (!$this->dao->update($uid, ['exp' => bcsub($old_exp, $exp, 2)])) {
            throw new AdminException(400695);
        }

        return true;
    }

    /**
     * 获取用户标签
     *
     * @param $uid
     */
    public function getUserLablel(array $uids)
    {
        /** @var UserLabelRelationServices $services */
        $services = app(UserLabelRelationServices::class);
        $userlabels = $services->getUserLabelList($uids);
        $data = [];
        foreach ($uids as $uid) {
            $labels = array_filter($userlabels, function ($item) use ($uid) {
                if ($item['id'] == $uid) {
                    return true;
                }
            });
            $data[$uid] = implode(',', array_column($labels, 'label_name'));
        }

        return $data;
    }

    /**
     * 显示资源列表头部
     *
     * @return array[]
     */
    public function typeHead()
    {
        // 全部会员
        $all = $this->getCount([]);
        /** @var UserWechatuserServices $userWechatUser */
        $userWechatUser = app(UserWechatuserServices::class);
        // 小程序会员
        $routine = $userWechatUser->getCount([['w.user_type', '=', 'routine']]);
        // 公众号会员
        $wechat = $userWechatUser->getCount([['w.user_type', '=', 'wechat']]);
        // H5会员
        $h5 = $userWechatUser->getCount(['w.openid' => '', 'u.user_type' => 'h5']);
        // pc会员
        $pc = $userWechatUser->getCount(['w.openid' => '', 'u.user_type' => 'pc']);

        return [
            ['user_type' => '', 'name' => '全部会员', 'count' => $all],
            ['user_type' => 'routine', 'name' => '小程序会员', 'count' => $routine],
            ['user_type' => 'wechat', 'name' => '公众号会员', 'count' => $wechat],
            ['user_type' => 'h5', 'name' => 'H5会员', 'count' => $h5],
            ['user_type' => 'pc', 'name' => 'PC会员', 'count' => $pc],
        ];
    }

    /**
     * 会员列表
     *
     * @param array $where
     *
     * @return array
     */
    public function index(array $where)
    {
        $apps = SystemApp::idToNameMap();
        [$list, $count] = $this->getUserList($where);
        $platforms = SystemApp::platformsMap();
        $channels = SystemApp::marketChannelsMap();

        if ($list) {
            foreach ($list as &$item) {
                // 用户类型
                $item['user_type'] = $item['is_vip'] ? '会员用户' : '普通用户';
                // 等级名称
                $item['level'] = $levelName[$item['level']] ?? '无';
                // 分组名称
                $item['group_id'] = $userGroup[$item['group_id']] ?? '无';
                // 会员类型
                $item['vip_name'] = User::vipTypeMap()[$item['vip_type']] ?? '';
                $item['labels'] = $userlabel[$item['id']] ?? '';
                $item['overdue_time'] = $item['is_vip'] ? Carbon::parse($item['overdue_time'])->toDateString() : '';
                // 应用名称
                $item['app_name'] = $apps[$item['app_id']] ?? '';
                // 应用平台、渠道
                $item['platform'] = $platforms[$item['platform']] ?? '';
                $item['market_channel'] = $channels[$item['market_channel']] ?? '';
                // 最后登录时间
                $item['last_time'] = $item['last_time'] ? date('Y-m-d H:i:s', $item['last_time']) : '';
            }
        }

        return compact('count', 'list');
    }

    /**
     * 获取修改页面数据
     *
     * @param int $id
     *
     * @return array
     * @throws AdminException
     */
    public function edit(int $id): array
    {
        $user = $this->getUserInfo($id);
        if (!$user) {
            throw new AdminException(100026);
        }
        $f = [];
        $f[] = Form::input('id', '用户编号', $user['id'])->disabled(true);
        $f[] = Form::input('account', '账号', $user['account'])->disabled(true);
        $f[] = Form::input('real_name', '第三方账号', $user['real_name'])->disabled(true);
        $f[] = Form::input('password', '登录密码')->type('password')->placeholder('不改密码请留空');
        $f[] = Form::input('nickname', '昵称', $user['nickname'])->disabled(false);
        $f[] = Form::radio('is_vip', '用户类型', (int) $user['is_vip'])->options([['value' => 0, 'label' => '普通用户'], ['value' => 1, 'label' => '会员用户']]);
        $f[] = Form::date('overdue_time', '会员时效', $user['overdue_time'] ? date('Y-m-d', $user['overdue_time']) : '');
        $f[] = Form::select('app_id', '所属应用', $user['app_id'])->options(FormOptions::systemApps())->disabled(true);
        $f[] = Form::input('app_version', '版本号', $user['app_version'])->disabled(true);
        $f[] = Form::input('platform', '渠道', $user['platform'])->disabled(true);
        $f[] = Form::input('os_version', '操作系统', $user['os_version'])->disabled(true);
        $f[] = Form::input('region', '国家地区', $user['region'])->disabled(true);
        $f[] = Form::input('reg_ip', '注册IP', $user['reg_ip'])->disabled(true);
        $f[] = Form::input('reg_time', '注册时间', Carbon::parse($user['reg_time'])->toDateTimeString())->disabled(true);
        $f[] = Form::input('last_time', '最后登录时间', empty($user['last_time']) ? '' : Carbon::parse($user['last_time'])->toDateTimeString())->disabled(true);
        $f[] = Form::radio('status', '用户状态', (int) $user['status'])->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '锁定']]);
        $f[] = Form::textarea('remark', '用户备注', $user['remark']);

        return create_form('编辑', $f, url('/admin/user/user/' . $id), 'PUT');
    }

    /**
     * 添加用户表单
     *
     * @param int $id
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function saveForm()
    {
        $f = [];
        $f[] = Form::input('real_name', '真实姓名', '')->placeholder('请输入真实姓名');
        $f[] = Form::input('phone', '手机号码', '')->placeholder('请输入手机号码')->required();
        $f[] = Form::date('birthday', '生日', '')->placeholder('请选择生日');
        $f[] = Form::input('card_id', '身份证号', '')->placeholder('请输入身份证号');
        $f[] = Form::input('addres', '用户地址', '')->placeholder('请输入用户地址');
        $f[] = Form::textarea('mark', '用户备注', '')->placeholder('请输入用户备注');
        $f[] = Form::input('pwd', '登录密码')->type('password')->placeholder('请输入登录密码');
        $f[] = Form::input('true_pwd', '确认密码')->type('password')->placeholder('请再次确认密码');
        $systemLevelList = app(SystemUserLevelServices::class)->getWhereLevelList([], 'id,name');
        $setOptionLevel = function () use ($systemLevelList) {
            $menus = [];
            foreach ($systemLevelList as $menu) {
                $menus[] = ['value' => $menu['id'], 'label' => $menu['name']];
            }

            return $menus;
        };
        $f[] = Form::select('level', '用户等级', '')->setOptions(FormBuilder::setOptions($setOptionLevel))->filterable(true);
        $systemGroupList = app(UserGroupServices::class)->getGroupList();
        $setOptionGroup = function () use ($systemGroupList) {
            $menus = [];
            foreach ($systemGroupList as $menu) {
                $menus[] = ['value' => $menu['id'], 'label' => $menu['group_name']];
            }

            return $menus;
        };
        $f[] = Form::select('group_id', '用户分组', '')->setOptions(FormBuilder::setOptions($setOptionGroup))->filterable(true);
        $systemLabelList = app(UserLabelServices::class)->getLabelList();
        $setOptionLabel = function () use ($systemLabelList) {
            $menus = [];
            foreach ($systemLabelList as $menu) {
                $menus[] = ['value' => $menu['id'], 'label' => $menu['label_name']];
            }

            return $menus;
        };
        $f[] = Form::select('label_id', '用户标签', '')->setOptions(FormBuilder::setOptions($setOptionLabel))->filterable(true)->multiple(true);
        $f[] = Form::radio('spread_open', '推广资格', 1)->info('禁用用户的推广资格后，在任何分销模式下该用户都无分销权限')->options([['value' => 1, 'label' => '启用'], ['value' => 0, 'label' => '禁用']]);
        // 分销模式  人人分销
        $storeBrokerageStatus = sys_config('store_brokerage_statu', 1);
        if ($storeBrokerageStatus == 1) {
            $f[] = Form::radio('is_promoter', '推广员权限', 0)->info('指定分销模式下，开启或关闭用户的推广权限')->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        }
        $f[] = Form::radio('status', '用户状态', 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '锁定']]);

        return create_form('添加用户', $f, url('/admin/user/user'), 'POST');
    }

    /**
     * 修改提交处理
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function updateInfo(int $id, array $data)
    {
        $user = $this->getUserInfo($id);
        if (!$user) {
            throw new AdminException(100026);
        }
        $res1 = false;
        $res2 = false;
        $edit = [];
        if ($data['money_status'] && $data['money']) {// 余额增加或者减少
            /** @var UserMoneyServices $userMoneyServices */
            $userMoneyServices = app(UserMoneyServices::class);
            if ($data['money_status'] == 1) {// 增加
                $edit['now_money'] = bcadd($user['now_money'], $data['money'], 2);
                $res1 = $userMoneyServices->income('system_add', $user['id'], $data['money'], $edit['now_money'], $data['adminId'] ?? 0);
            } else {
                if ($data['money_status'] == 2) {// 减少
                    if ($user['now_money'] > $data['money']) {
                        $edit['now_money'] = bcsub($user['now_money'], $data['money'], 2);
                    } else {
                        $edit['now_money'] = 0;
                        $data['money'] = $user['now_money'];
                    }
                    $res1 = $userMoneyServices->income('system_sub', $user['id'], $data['money'], $edit['now_money'], $data['adminId'] ?? 0);
                }
            }
            event('out.outPush', ['user_update_push', ['id' => $id, 'type' => 'money', 'value' => $data['money_status'] == 2 ? -floatval($data['money']) : $data['money']]]);
        } else {
            $res1 = true;
        }
        if ($data['integration_status'] && $data['integration']) {// 积分增加或者减少
            /** @var UserBillServices $userBill */
            $userBill = app(UserBillServices::class);
            $integral_data = ['link_id' => $data['adminId'] ?? 0, 'number' => $data['integration']];
            if ($data['integration_status'] == 1) {// 增加
                $edit['integral'] = bcadd($user['integral'], $data['integration'], 2);
                $integral_data['balance'] = $edit['integral'];
                $integral_data['title'] = '系统增加积分';
                $integral_data['mark'] = '系统增加了' . floatval($data['integration']) . '积分';
                $res2 = $userBill->incomeIntegral($user['id'], 'system_add', $integral_data);
            } else {
                if ($data['integration_status'] == 2) {// 减少
                    $edit['integral'] = bcsub($user['integral'], $data['integration'], 2);
                    $integral_data['balance'] = $edit['integral'];
                    $integral_data['title'] = '系统减少积分';
                    $integral_data['mark'] = '系统扣除了' . floatval($data['integration']) . '积分';
                    $res2 = $userBill->expendIntegral($user['id'], 'system_sub', $integral_data);
                }
            }
            event('out.outPush', ['user_update_push', ['id' => $id, 'type' => 'point', 'value' => $data['integration_status'] == 2 ? -intval($data['integration']) : $data['integration']]]);
        } else {
            $res2 = true;
        }
        // 修改基本信息
        if (!isset($data['is_other']) || !$data['is_other']) {
            app(UserLabelRelationServices::class)->setUserLable([$id], $data['label_id']);
            if (isset($data['pwd']) && $data['pwd'] && $data['pwd'] != $user['pwd']) {
                $edit['pwd'] = $data['pwd'];
            }
            if (isset($data['spread_open'])) {
                $edit['spread_open'] = $data['spread_open'];
            }
            $edit['status'] = $data['status'];
            $edit['real_name'] = $data['real_name'];
            $edit['card_id'] = $data['card_id'];
            $edit['birthday'] = strtotime($data['birthday']);
            $edit['mark'] = $data['mark'];
            $edit['is_promoter'] = $data['is_promoter'];
            $edit['level'] = $data['level'];
            $edit['phone'] = $data['phone'];
            $edit['addres'] = $data['addres'];
            $edit['group_id'] = $data['group_id'];
            if ($user['level'] != $data['level']) {
                /** @var UserLevelServices $userLevelServices */
                $userLevelServices = app(UserLevelServices::class);
                $userLevelServices->setUserLevel((int) $user['id'], (int) $data['level']);
            }
        }
        if ($edit) {
            $res3 = $this->dao->update($id, $edit);
        } else {
            $res3 = true;
        }
        if ($res1 && $res2 && $res3) {
            return true;
        } else {
            throw new AdminException(100007);
        }
    }

    /**
     * 编辑其他
     */
    public function editOther($id)
    {
        $user = $this->getUserInfo($id);
        if (!$user) {
            throw new AdminException(100026);
        }
        $f = [];
        $f[] = Form::radio('money_status', '修改余额', 1)->options([['value' => 1, 'label' => '增加'], ['value' => 2, 'label' => '减少']]);
        $f[] = Form::number('money', '余额', 0)->min(0)->max(999999.99);
        $f[] = Form::radio('integration_status', '修改积分', 1)->options([['value' => 1, 'label' => '增加'], ['value' => 2, 'label' => '减少']]);
        $f[] = Form::number('integration', '积分', 0)->min(0)->precision(0)->max(999999);

        return create_form('修改其他', $f, url('/admin/user/update_other/' . $id), 'PUT');
    }

    /**
     * 设置会员分组
     *
     * @param $id
     *
     * @return mixed
     */
    public function setgroupBy($uids)
    {
        /** @var UserGroupServices $groupServices */
        $groupServices = app(UserGroupServices::class);
        $userGroup = $groupServices->getGroupList();
        if (count($uids) == 1) {
            $user = $this->getUserInfo($uids[0], ['group_id']);
            $setOptionUserGroup = function () use ($userGroup) {
                $menus = [];
                foreach ($userGroup as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['group_name']];
                }

                return $menus;
            };
            $field[] = Form::select('group_id', '用户分组', $user->getData('group_id'))->setOptions(FormBuilder::setOptions($setOptionUserGroup))->filterable(true);
        } else {
            $setOptionUserGroup = function () use ($userGroup) {
                $menus = [];
                foreach ($userGroup as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['group_name']];
                }

                return $menus;
            };
            $field[] = Form::select('group_id', '用户分组')->setOptions(FormBuilder::setOptions($setOptionUserGroup))->filterable(true);
        }
        $field[] = Form::hidden('uids', implode(',', $uids));

        return create_form('设置用户分组', $field, url('/user/save_set_group'), 'PUT');
    }

    /**
     * 保存会员分组
     *
     * @param $id
     *
     * @return mixed
     */
    public function saveSetgroupBy($uids, int $group_id)
    {
        /** @var UserGroupServices $userGroup */
        $userGroup = app(UserGroupServices::class);
        if (!$userGroup->getgroupBy($group_id)) {
            throw new AdminException(400696);
        }
        if (!$this->setUsergroupBy($uids, $group_id)) {
            throw new AdminException(400697);
        }

        return true;
    }

    /**
     * 设置用户标签
     *
     * @param $uids
     *
     * @return mixed
     */
    public function setLabel($uids)
    {
        /** @var UserLabelServices $labelServices */
        $labelServices = app(UserLabelServices::class);
        $userLabel = $labelServices->getLabelList();
        if (count($uids) == 1) {
            $lids = app(UserLabelRelationServices::class)->getUserLabels($uids[0]);
            $setOptionUserLabel = function () use ($userLabel) {
                $menus = [];
                foreach ($userLabel as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['label_name']];
                }

                return $menus;
            };
            $field[] = Form::select('label_id', '用户标签', $lids)->setOptions(FormBuilder::setOptions($setOptionUserLabel))->filterable(true)->multiple(true);
        } else {
            $setOptionUserLabel = function () use ($userLabel) {
                $menus = [];
                foreach ($userLabel as $menu) {
                    $menus[] = ['value' => $menu['id'], 'label' => $menu['label_name']];
                }

                return $menus;
            };
            $field[] = Form::select('label_id', '用户标签')->setOptions(FormBuilder::setOptions($setOptionUserLabel))->filterable(true)->multiple(true);
        }
        $field[] = Form::hidden('uids', implode(',', $uids));

        return create_form('设置用户标签', $field, url('/user/save_set_label'), 'PUT');
    }

    /**
     * 保存用户标签
     *
     * @return mixed
     */
    public function saveSetLabel($uids, $lable_id)
    {
        foreach ($lable_id as $id) {
            if (!app(UserLabelServices::class)->getLable((int) $id)) {
                throw new AdminException(400698);
            }
        }
        /** @var UserLabelRelationServices $services */
        $services = app(UserLabelRelationServices::class);
        if (!$services->setUserLable($uids, $lable_id)) {
            throw new AdminException(400668);
        }

        return true;
    }

    /**
     * 赠送会员等级
     *
     * @param int $uid
     *
     * @return mixed
     * */
    public function giveLevel($id)
    {
        if (!$this->getUserInfo($id)) {
            throw new AdminException(400214);
        }
        // 查询高于当前会员的所有会员等级
        $grade = app(UserLevelServices::class)->getUerLevelInfoByUid($id, 'grade');
        $systemLevelList = app(SystemUserLevelServices::class)->getWhereLevelList(['grade', '>', $grade ?? 0], 'id,name');

        $setOptionlevel = function () use ($systemLevelList) {
            $menus = [];
            foreach ($systemLevelList as $menu) {
                $menus[] = ['value' => $menu['id'], 'label' => $menu['name']];
            }

            return $menus;
        };
        $field[] = Form::select('level_id', '用户等级')->setOptions(FormBuilder::setOptions($setOptionlevel))->filterable(true);

        return create_form('赠送等级', $field, url('/user/save_give_level/' . $id), 'PUT');
    }

    /**
     * 执行赠送会员等级
     *
     * @param int $uid
     *
     * @return mixed
     * */
    public function saveGiveLevel(int $id, int $level_id)
    {
        if (!$this->getUserInfo($id)) {
            throw new AdminException(400214);
        }
        /** @var SystemUserLevelServices $systemLevelServices */
        $systemLevelServices = app(SystemUserLevelServices::class);
        /** @var UserLevelServices $userLevelServices */
        $userLevelServices = app(UserLevelServices::class);
        // 查询当前选择的会员等级
        $systemLevel = $systemLevelServices->getLevel($level_id);
        if (!$systemLevel) {
            throw new AdminException(400699);
        }
        // 检查是否拥有此会员等级
        $level = $userLevelServices->getWhereLevel(['id' => $id, 'level_id' => $level_id], 'valid_time,is_forever');
        if ($level && $level['status'] == 1 && $level['is_del'] == 0) {
            throw new AdminException(400700);
        }
        // 保存会员信息
        if (!$userLevelServices->setUserLevel($id, $level_id, $systemLevel)) {
            throw new AdminException(400219);
        }

        return true;
    }

    /**
     * 赠送付费会员时长
     *
     * @param int $uid
     *
     * @return mixed
     * */
    public function giveLevelTime($id)
    {
        if (!$this->getUserInfo($id)) {
            throw new AdminException(400214);
        }
        $field[] = Form::number('days', '增加时长(天)')->precision(0)->style(['width' => '200px'])->required();

        return create_form('赠送付费会员时长', $field, url('/user/save_give_level_time/' . $id), 'PUT');
    }

    /**
     * 执行赠送付费会员时长
     *
     * @param int $uid
     *
     * @return mixed
     * */
    public function saveGiveLevelTime(int $id, int $days)
    {
        $userInfo = $this->getUserInfo($id);
        if (!$userInfo) {
            throw new AdminException(400214);
        }
        if ($days == 0) {
            throw new AdminException(400701);
        }
        if ($days < -1) {
            throw new AdminException(400702);
        }
        if ($userInfo->is_money_level == 0) {
            $userInfo->is_money_level = 3;
            if ($days == -1) {
                $userInfo->is_ever_level = 1;
                $time = 0;
            } else {
                $userInfo->overdue_time = $time = time() + ($days * 86400);
            }
        } else {
            if ($days == -1) {
                $userInfo->is_ever_level = 1;
                $time = 0;
            } else {
                $userInfo->overdue_time = $time = $userInfo->overdue_time + ($days * 86400);
            }
        }
        $userInfo->save();
        /** @var StoreOrderCreateServices $storeOrderCreateServices */
        $storeOrderCreateServices = app(StoreOrderCreateServices::class);
        $orderInfo = [
            'id' => $id,
            'order_id' => $storeOrderCreateServices->getNewOrderId(),
            'type' => 3,
            'member_type' => 0,
            'pay_type' => 'admin',
            'paid' => 1,
            'pay_time' => time(),
            'is_free' => 1,
            'overdue_time' => $time,
            'vip_day' => $days,
            'add_time' => time(),
        ];
        /** @var OtherOrderServices $otherOrder */
        $otherOrder = app(OtherOrderServices::class);
        $otherOrder->save($orderInfo);

        return true;
    }

    /**
     * 清除会员等级
     *
     * @paran int $uid
     * @paran boolean
     * */
    public function cleanUpLevel($uid)
    {
        if (!$this->getUserInfo($uid)) {
            throw new AdminException(400214);
        }
        /** @var UserLevelServices $services */
        $services = app(UserLevelServices::class);

        return \DB::transaction(function () use ($uid, $services) {
            $res = $services->delUserLevel($uid);
            $res1 = $this->dao->update($uid, ['clean_time' => time(), 'level' => 0, 'exp' => 0], 'id');
            if (!$res && !$res1) {
                throw new AdminException(400186);
            }

            return true;
        });
    }

    /**
     * 用户详细信息
     *
     * @param $uid
     */
    public function getUserDetailed(int $uid, $userIfno = [])
    {
        /** @var UserAddressServices $userAddress */
        $userAddress = app(UserAddressServices::class);
        $field = 'real_name,phone,province,city,district,detail,post_code';
        $address = $userAddress->getUserDefaultAddress($uid, $field);
        if (!$address) {
            $address = $userAddress->getUserAddressList($uid, $field);
            $address = $address[0] ?? [];
        }
        $userInfo = $this->getUserInfo($uid);

        return [
            [
                'name' => '默认收货地址',
                'value' => $address
                    ? '收货人:' . $address['real_name'] . '邮编:' . $address['post_code'] . ' 收货人电话:' . $address['phone'] . ' 地址:' . $address['province'] . ' ' . $address['city'] . ' ' . $address['district'] . ' ' . $address['detail'] : '',
            ],
            ['name' => '手机号码', 'value' => $userInfo['phone']],
            ['name' => '姓名', 'value' => ''],
            ['name' => '微信昵称', 'value' => $userInfo['nickname']],
            ['name' => '头像', 'value' => $userInfo['avatar']],
            ['name' => '邮箱', 'value' => ''],
            ['name' => '生日', 'value' => ''],
            ['name' => '积分', 'value' => $userInfo['integral']],
            ['name' => '上级推广人', 'value' => $userInfo['spread_uid'] ? $this->getUserInfo($userInfo['spread_uid'], ['nickname'])['nickname'] ?? '' : ''],
            ['name' => '账户余额', 'value' => $userInfo['now_money']],
            ['name' => '佣金总收入', 'value' => app(UserBillServices::class)->getBrokerageSum($uid)],
            ['name' => '提现总金额', 'value' => app(UserExtractServices::class)->getUserExtract($uid)],
        ];
    }

    /**
     * 获取用户详情里面的用户消费能力和用户余额积分等
     *
     * @param $uid
     *
     * @return array[]
     */
    public function getHeaderList(int $uid, $userInfo = [])
    {
        if (!$userInfo) {
            $userInfo = $this->getUserInfo($uid);
        }
        /** @var StoreOrderServices $orderServices */
        $orderServices = app(StoreOrderServices::class);
        $where = ['id' => $uid, 'paid' => 1, 'refund_status' => 0, 'pid' => 0];

        return [
            [
                'title' => '余额',
                'value' => $userInfo['now_money'] ?? 0,
                'key' => '元',
            ],
            [
                'title' => '总计订单',
                'value' => $orderServices->count($where),
                'key' => '笔',
            ],
            [
                'title' => '总消费金额',
                'value' => $orderServices->together($where, 'pay_price'),
                'key' => '元',
            ],
            [
                'title' => '积分',
                'value' => $userInfo['integral'] ?? 0,
                'key' => '',
            ],
            [
                'title' => '本月订单',
                'value' => $orderServices->count($where + ['time' => 'month']),
                'key' => '笔',
            ],
            [
                'title' => '本月消费金额',
                'value' => $orderServices->together($where + ['time' => 'month'], 'pay_price'),
                'key' => '元',
            ],
        ];
    }

    /**
     * 获取用户记录里的积分总数和签到总数和余额变动总数
     *
     * @param $uid
     *
     * @return array
     */
    public function getUserBillCountData($uid)
    {
        /** @var UserBillServices $userBill */
        $userBill = app(UserBillServices::class);
        $integral_count = $userBill->getIntegralCount($uid);
        $sign_count = $userBill->getSignCount($uid);
        $balanceChang_count = $userBill->getBrokerageCount($uid);

        return [$integral_count, $sign_count, $balanceChang_count];
    }

    public function read(int $uid)
    {
        $userInfo = $this->getUserInfo($uid);
        if (!$userInfo) {
            throw new AdminException(100026);
        }
        $info = [
            'id' => $uid,
            'userinfo' => $this->getUserDetailed($uid, $userInfo),
            'headerList' => $this->getHeaderList($uid, $userInfo),
            'count' => $this->getUserBillCountData($uid),
            'ps_info' => $userInfo,
        ];

        return $info;
    }

    /**
     * 获取好友
     *
     * @param int $id
     * @param string $field
     *
     * @return array
     */
    public function getFriendList(int $id, string $field = 'uid,nickname,level,add_time,spread_time')
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList(['spread_uid' => $id], $field, $page, $limit);
        /** @var SystemUserLevelServices $systemLevelServices */
        $systemLevelServices = app(SystemUserLevelServices::class);
        $systemLevelList = $systemLevelServices->getWhereLevelList([], 'id,name');
        if ($systemLevelServices) {
            $systemLevelServices = array_combine(array_column($systemLevelList, 'id'), $systemLevelList);
        }
        foreach ($list as &$item) {
            $item['type'] = $systemLevelServices[$item['level']]['name'] ?? '暂无';
            $item['add_time'] = $item['spread_time'] && is_numeric($item['spread_time']) ? date('Y-m-d H:i:s', $item['spread_time']) : '';
        }
        $count = $this->dao->count(['spread_uid' => $id]);

        return compact('list', 'count');
    }

    /**
     * 获取单个用户信息
     *
     * @param $id 用户id
     *
     * @return mixed
     */
    public function oneUserInfo(int $id, string $type)
    {
        switch ($type) {
            case 'spread':
                //                /** @var UserFriendsServices $services */
                //                $services = app(UserFriendsServices::class);
                //                return $services->getFriendList(['id' => $id], ['level', 'nickname']);
                return $this->getFriendList($id);
            case 'order':
                /** @var StoreOrderServices $services */
                $services = app(StoreOrderServices::class);

                return $services->getUserOrderList($id);
            case 'integral':
                /** @var UserBillServices $services */
                $services = app(UserBillServices::class);

                return $services->getIntegralList($id, [], 'title,number,balance,mark,add_time,frozen_time');
            case 'sign':
                /** @var UserBillServices $services */
                $services = app(UserBillServices::class);

                return $services->getSignList($id, [], 'title,number,mark,add_time');
            case 'balance_change':
                /** @var UserMoneyServices $services */
                $services = app(UserMoneyServices::class);

                return $services->balanceList(['id' => $id]);
            default:
                throw new AdminException(100100);
        }
    }

    /**获取特定时间用户访问量
     *
     * @param $time
     * @param $week
     *
     * @return int
     */
    public function todayLastVisits($time, $week)
    {
        return $this->dao->todayLastVisit($time, $week);
    }

    /**获取特定时间新增用户
     *
     * @param $time
     * @param $week
     *
     * @return int
     */
    public function todayAddVisits($time, $week, $authSearch = [])
    {
        return $this->dao->todayAddVisit($time, $week, $authSearch);
    }

    /**
     * 用户图表
     */
    public function userChart($authSearch = [])
    {
        $startTime = today()->subDays(29)->startOfDay()->unix();
        $endTime = today()->endOfDay()->unix();

        $user_list = $this->dao->userList($startTime, $endTime, $authSearch);
        $chartdata = [];
        $data = [];
        $chartdata['legend'] = ['用户数'];     // 分类
        $chartdata['yAxis']['maxnum'] = 0;  // 最大值数量
        $chartdata['xAxis'] = [date('m-d')];// X轴值
        $chartdata['series'] = [0];         // 分类1值
        if (!empty($user_list)) {
            foreach ($user_list as $k => $v) {
                $data['day'][] = $v['day'];
                $data['count'][] = $v['count'];
                if ($chartdata['yAxis']['maxnum'] < $v['count']) {
                    $chartdata['yAxis']['maxnum'] = $v['count'];
                }
            }
            $chartdata['xAxis'] = $data['day'];   // X轴值
            $chartdata['series'] = $data['count'];// 分类1值
        }
        $chartdata['bing_xdata'] = ['未消费用户', '消费一次用户', '留存客户', '回流客户'];
        $color = ['#5cadff', '#b37feb', '#19be6b', '#ff9900'];
        // $pay[0] = $this->dao->count(['pay_count' => 0]);
        // $pay[1] = $this->dao->count(['pay_count' => 1]);
        // $pay[2] = $this->dao->userCount(1);
        // $pay[3] = $this->dao->userCount(2);
        // foreach ($pay as $key => $item) {
        //     $bing_data[] = ['name' => $chartdata['bing_xdata'][$key], 'value' => $pay[$key], 'itemStyle' => ['color' => $color[$key]]];
        // }
        // $chartdata['bing_data'] = $bing_data;
        return $chartdata;
    }

    /**
     * 用户资金统计
     *
     * @param int $uid
     */
    public function balance(int $uid)
    {
        $userInfo = $this->getUserInfo($uid);
        if (!$userInfo) {
            throw new AdminException(400214);
        }
        /** @var UserBillServices $userBill */
        $userBill = app(UserBillServices::class);
        $user['now_money'] = $userInfo['now_money'];                                                         // 当前总资金
        $user['recharge'] = $userBill->getRechargeSum($uid);                                                 // 累计充值
        $user['orderStatusSum'] = 0;                                                                         // 累计消费

        return $user;
    }

    /**
     * 获取推广人排行
     */
    public function getRankList(array $data)
    {
        $startTime = strtotime('this week Monday');
        $endTime = time();
        switch ($data['type']) {
            case 'week':
                $startTime = strtotime('this week Monday');
                break;
            case 'month':
                $startTime = strtotime('last month');
                break;
        }
        [$page, $limit] = $this->getPageValue();
        $field = 't0.uid,t0.spread_uid,count(t1.spread_uid) AS count,t0.add_time,t0.nickname,t0.avatar';

        return $this->dao->getAgentRankList([$startTime, $endTime], $field, $page, $limit);
    }

    /**
     * 静默绑定推广人
     *
     * @param int $uid
     * @param int $spreadUid
     * @param $code
     *
     * @return bool
     */
    public function spread(int $uid, int $spreadUid, $code)
    {
        $userInfo = $this->getUserInfo($uid);
        if (!$userInfo) {
            throw new AdminException(100026);
        }
        if ($code && !$spreadUid) {
            /** @var QrcodeServices $qrCode */
            $qrCode = app(QrcodeServices::class);
            if ($info = $qrCode->getOne(['id' => $code, 'status' => 1])) {
                $spreadUid = $info['third_id'];
            }
        }
        if ($spreadUid == 0) {
            return '不绑定';
        }
        $userSpreadUid = $this->dao->value(['id' => $spreadUid], 'spread_uid');
        // 记录好友关系
        if ($spreadUid && $uid && $spreadUid != $uid) {
            /** @var UserFriendsServices $serviceFriend */
            $serviceFriend = app(UserFriendsServices::class);
            $serviceFriend->saveFriend([
                'id' => $uid,
                'friends_uid' => $spreadUid,
            ]);
        }
        $check = false;
        if (sys_config('brokerage_bindind') == 1) {
            if (sys_config('store_brokerage_binding_status') == 1) {
                if (!$userInfo['spread_uid']) {
                    $check = true;
                }
            } elseif (sys_config('store_brokerage_binding_status') == 2 && (($userInfo['spread_time'] + (sys_config('store_brokerage_binding_time') * 86400)) < time())) {
                $check = true;
            } elseif (sys_config('store_brokerage_binding_status') == 3) {
                $check = true;
            }
        } elseif (sys_config('brokerage_bindind') == 2) {
            if ($userInfo['add_time'] == $userInfo['last_time']) {
                $check = true;
            }
        }
        if ($userInfo['id'] == $userSpreadUid || $userInfo['spread_uid'] == $spreadUid) {
            $check = false;
        }
        if ($check) {
            $spreadInfo = $this->dao->get($spreadUid);
            $data = [];
            $data['spread_uid'] = $spreadUid;
            $data['spread_time'] = time();
            $data['division_id'] = $spreadInfo['division_id'];
            $data['agent_id'] = $spreadInfo['agent_id'];
            $data['staff_id'] = $spreadInfo['staff_id'];
            if (!$this->dao->update($uid, $data, 'id')) {
                throw new AdminException(410288);
            }

            return '绑定上级成功，上级uid为' . $spreadUid;
        } else {
            return '不绑定';
        }
    }

    /**
     * 获取用户下级推广人
     *
     * @param int $uid 当前用户
     * @param int $grade 等级  0  一级 1 二级
     * @param string $orderBy 排序
     * @param string $keyword
     *
     * @return array|bool
     */
    public function getUserSpreadGrade(int $uid = 0, $grade = 0, $orderBy = '', $keyword = '')
    {
        $user = $this->getUserInfo($uid);
        if (!$user) {
            throw new AdminException(400214);
        }
        $spread_one_ids = $this->getUserSpredadUids($uid, 1);
        $spread_two_ids = $this->getUserSpredadUids($uid, 2);
        $data = [
            'total' => count($spread_one_ids),
            'totalLevel' => count($spread_two_ids),
            'list' => [],
        ];
        if (sys_config('brokerage_level', 2) == 1) {
            $data['count'] = $data['total'];
        } else {
            $data['count'] = $data['total'] + $data['totalLevel'];
        }
        /** @var UserStoreOrderServices $userStoreOrder */
        $userStoreOrder = app(UserStoreOrderServices::class);
        $list = [];
        if ($grade == 0) {
            if ($spread_one_ids) {
                $list = $userStoreOrder->getUserSpreadCountList($spread_one_ids, $orderBy, $keyword);
            }
        } else {
            if ($spread_two_ids) {
                $list = $userStoreOrder->getUserSpreadCountList($spread_two_ids, $orderBy, $keyword);
            }
        }
        foreach ($list as &$item) {
            if (isset($item['spread_time']) && $item['spread_time']) {
                $item['time'] = date('Y/m/d', $item['spread_time']);
            }
        }
        $data['list'] = $list;
        $data['brokerage_level'] = (int) sys_config('brokerage_level', 2);

        return $data;
    }

    /**
     * 获取推广人uids
     *
     * @param int $uid
     * @param bool $one
     *
     * @return array
     */
    public function getUserSpredadUids(int $uid, int $type = 0)
    {
        $uids = $this->dao->getColumn(['spread_uid' => $uid, 'is_del' => 0], 'id');
        if ($type === 1) {
            return $uids;
        }
        if ($uids) {
            $uidsTwo = $this->dao->getColumn([['spread_uid', 'in', $uids], ['is_del', '=', 0]], 'id');
            if ($type === 2) {
                return $uidsTwo;
            }
            if ($uidsTwo) {
                $uids = array_merge($uids, $uidsTwo);
            }
        }

        return $uids;
    }

    /** 修改会员的时间及是否会员状态
     *
     * @param int $vip_day 会员天数
     * @param array $user_id 用户id
     * @param int $is_money_level 会员来源途径
     * @param bool $member_type 会员卡类型
     *
     * @return mixed
     */
    public function setMemberOverdueTime($vip_day, int $user_id, int $is_money_level, $member_type = false)
    {
        if ($vip_day == 0) {
            throw new AdminException(410289);
        }
        $user_info = $this->getUserInfo($user_id);
        if (!$user_info) {
            throw new AdminException(410032);
        }
        if (!$member_type) {
            $member_type = "month";
        }
        if ($member_type == 'ever') {
            $overdue_time = 0;
            $is_ever_level = 1;
        } else {
            if ($user_info['is_money_level'] == 0) {
                $overdue_time = bcadd(bcmul($vip_day, 86400, 0), time(), 0);
            } else {
                $overdue_time = bcadd(bcmul($vip_day, 86400, 0), $user_info['overdue_time'], 0);
            }
            $is_ever_level = 0;
        }
        $setData['overdue_time'] = $overdue_time;
        $setData['is_ever_level'] = $is_ever_level;
        $setData['is_money_level'] = $is_money_level ?: 0;

        // if ($user_info['level'] == 0) $setData['level'] = 1;
        return $this->dao->update(['id' => $user_id], $setData);
    }
}
