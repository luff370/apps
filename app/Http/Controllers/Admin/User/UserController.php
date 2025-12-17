<?php

namespace App\Http\Controllers\Admin\User;

use Illuminate\Http\Request;
use App\Services\User\UserServices;
use App\Http\Controllers\Admin\Controller;

class UserController extends Controller
{
    /**
     * user constructor.
     *
     * @param UserServices $services
     */
    public function __construct(UserServices $services)
    {
        $this->service = $services;
    }

    /**
     * 显示资源列表头部
     */
    public function type_header()
    {
        $list = $this->service->typeHead();

        return $this->success(compact('list'));
    }

    /**
     * 显示资源列表
     */
    public function index(Request $request)
    {
        return $this->success($this->service->index($request->all()));
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        return $this->success($this->service->saveForm());
    }

    /**
     * 添加编辑用户信息时候的信息
     *
     * @param int $uid
     */
    public function userSaveInfo($uid = 0)
    {
        $data = $this->service->getRow($uid);

        return $this->success($data);
    }

    /**
     * 保存新建的资源
     */
    public function save()
    {
        $data = $this->getMore([
            ['real_name', ''],
            ['phone', 0],
            ['birthday', ''],
            ['card_id', ''],
            ['addres', ''],
            ['mark', ''],
            ['pwd', ''],
            ['true_pwd', ''],
            ['level', 0],
            ['group_id', 0],
            ['label_id', []],
            ['spread_open', 1],
            ['is_promoter', 0],
            ['status', 0],
        ]);
        if ($data['phone']) {
            if (!check_phone($data['phone'])) {
                return $this->fail(400252);
            }
            if ($this->service->count(['phone' => $data['phone'], 'is_del' => 0])) {
                return $this->fail(400314);
            }
            if (trim($data['real_name']) != '') {
                $data['nickname'] = $data['real_name'];
            } else {
                $data['nickname'] = substr_replace($data['phone'], '****', 3, 4);
            }
        }
        if ($data['card_id']) {
            if (!check_card($data['card_id'])) {
                return $this->fail(400315);
            }
        }
        if ($data['pwd']) {
            if (!$data['true_pwd']) {
                return $this->fail(400263);
            }
            if ($data['pwd'] != $data['true_pwd']) {
                return $this->fail(400264);
            }
            $data['pwd'] = md5($data['pwd']);
        } else {
            unset($data['pwd']);
        }
        $data['avatar'] = sys_config('h5_avatar');
        $data['adminId'] = adminId();
        $data['user_type'] = 'h5';
        $label = $data['label_id'];
        unset($data['label_id']);
        foreach ($label as $k => $v) {
            if (!$v) {
                unset($label[$k]);
            }
        }
        $data['birthday'] = empty($data['birthday']) ? 0 : strtotime($data['birthday']);
        $data['add_time'] = time();
        $this->service->transaction(function () use ($data, $label) {
            $res = true;
            $userInfo = $this->service->save($data);
            $this->service->rewardNewUser((int) $userInfo->uid);
            if ($label) {
                $res = $this->service->saveSetLabel([$userInfo->uid], $label);
            }
            if ($data['level']) {
                $res = $this->service->saveGiveLevel((int) $userInfo->uid, (int) $data['level']);
            }
            if (!$res) {
                return $this->fail(100006);
            }
        });

        return $this->success(100021);
    }

    /**
     * 显示指定的资源
     *
     * @param int $id
     */
    public function show($id)
    {
        return $this->success($this->service->read(intval($id)));
    }

    /**
     * 赠送会员等级
     * */
    public function give_level($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->giveLevel((int) $id));
    }

    /**
     * 执行赠送会员等级
     * */
    public function save_give_level($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        [$level_id] = $this->getMore([
            ['level_id', 0],
        ], true);

        return $this->success($this->service->saveGiveLevel((int) $id, (int) $level_id) ? 400218 : 400219);
    }

    /**
     * 赠送付费会员时长
     * */
    public function give_level_time($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->giveLevelTime((int) $id));
    }

    /**
     * 执行赠送付费会员时长
     */
    public function save_give_level_time($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }
        [$days] = $this->getMore([
            ['days', 0],
        ], true);

        return $this->success($this->service->saveGiveLevelTime((int) $id, (int) $days) ? 400218 : 400219);
    }

    /**
     * 清除会员等级
     */
    public function del_level($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->cleanUpLevel((int) $id) ? 400185 : 400186);
    }

    /**
     * 设置会员分组
     */
    public function set_groupBy()
    {
        [$uids] = $this->getMore([
            ['uids', []],
        ], true);
        if (!$uids) {
            return $this->fail(100100);
        }

        return $this->success($this->service->setgroupBy($uids));
    }

    /**
     * 保存会员分组
     */
    public function save_set_groupBy()
    {
        [$group_id, $uids] = $this->getMore([
            ['group_id', 0],
            ['uids', ''],
        ], true);
        if (!$uids) {
            return $this->fail(100100);
        }
        if (!$group_id) {
            return $this->fail(400316);
        }
        $uids = explode(',', $uids);

        return $this->success($this->service->saveSetgroupBy($uids, (int) $group_id) ? 100014 : 100015);
    }

    /**
     * 设置用户标签
     */
    public function set_label()
    {
        [$uids] = $this->getMore([
            ['uids', []],
        ], true);
        $uid = implode(',', $uids);
        if (!$uid) {
            return $this->fail(100100);
        }

        return $this->success($this->service->setLabel($uids));
    }

    /**
     * 保存用户标签
     */
    public function save_set_label()
    {
        [$lables, $uids] = $this->getMore([
            ['label_id', []],
            ['uids', ''],
        ], true);
        if (!$uids) {
            return $this->fail(100100);
        }
        if (!$lables) {
            return $this->fail(400317);
        }
        $uids = explode(',', $uids);

        return $this->success($this->service->saveSetLabel($uids, $lables) ? 100014 : 100015);
    }

    /**
     * 编辑其他
     */
    public function edit_other($id)
    {
        return $this->success($this->service->editOther((int) $id));
    }

    /**
     * 执行编辑其他
     */
    public function update_other($id)
    {
        $data = $this->getMore([
            ['money_status', 0],
            ['money', 0],
            ['integration_status', 0],
            ['integration', 0],
        ]);

        $data['adminId'] = adminId();
        $data['money'] = (string) $data['money'];
        $data['integration'] = (string) $data['integration'];
        $data['is_other'] = true;

        return $this->success($this->service->updateInfo($id, $data) ? 100001 : 100007);
    }

    /**
     * 编辑会员信息
     */
    public function edit($id)
    {
        if (!$id) {
            return $this->fail(100100);
        }

        return $this->success($this->service->edit($id));
    }

    /**
     * 修改用户
     */
    public function update($id)
    {
        $data = $this->getMore([
            ['remark', ''],
            ['status', 1],
            ['is_vip', 0],
            ['password', ''],
            ['overdue_time', ''],
        ]);
        $data['overdue_time'] = strtotime($data['overdue_time']);

        if (!empty($data['phone'])) {
            if (!preg_match("/^1[3456789]\d{9}$/", $data['phone'])) {
                return $this->fail(400252);
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = md5($data['password']);
        } else {
            unset($data['password']);
        }

        $this->service->update($id, $data);

        return $this->success('修改成功');
    }

    public function setPassword($password): string
    {
        if (!empty($password)) {
            $password = md5($password);
        } else {
            $password = '';
        }

        return $password;
    }

    /**
     * 获取单个用户信息
     *
     * @param $id
     */
    public function oneUserInfo($id)
    {
        $data = $this->getMore([
            ['type', ''],
        ]);
        $id = (int) $id;
        if ($data['type'] == '') {
            return $this->fail(100100);
        }

        return $this->success($this->service->oneUserInfo($id, $data['type']));
    }

    /**
     * 同步微信粉丝用户
     */
    public function syncWechatUsers()
    {
        $this->service->syncWechatUsers();

        return $this->success(400318);
    }
}
