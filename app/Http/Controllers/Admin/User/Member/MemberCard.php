<?php

namespace App\Http\Controllers\Admin\User\Member;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\Member\MemberCardServices;
use App\Services\User\Member\MemberShipServices;
use App\Services\User\Member\MemberRightServices;

/**
 * Class MemberCard
 *
 * @package App\Http\Controllers\Admin\User\Member
 */
class MemberCard extends Controller
{
    /**
     * 初始化service层句柄
     * MemberCard constructor.
     *
     * @param MemberCardServices $memberCardServices
     */
    public function __construct(MemberCardServices $memberCardServices)
    {
        $this->service = $memberCardServices;
    }

    /**
     * 会员卡列表
     *
     * @param $card_batch_id
     */
    public function index($card_batch_id)
    {
        $where = $this->getMore([
            ['card_number', ""],
            ['phone', ""],
            ['card_batch_id', $card_batch_id],
            ['is_use', ""],
            ['is_status', ""],
            ['page', 1],
            ['limit', 20],
        ]);
        $data = $this->service->getSearchList($where);

        return $this->success($data);
    }

    /**
     * 会员分类
     */
    public function member_ship()
    {
        /** @var MemberShipServices $memberShipServices */
        $memberShipServices = app(MemberShipServices::class);
        $data = $memberShipServices->getSearchList();

        return $this->success($data);
    }

    /**
     * 保存分类
     *
     * @param $id
     * @param MemberShipServices $memberShipServices
     */
    public function ship_save($id, MemberShipServices $memberShipServices)
    {
        $data = $this->getMore([
            ['title', ''],
            ['price', ''],
            ['pre_price', ''],
            ['vip_day', ''],
            ['type', ''],
            ['sort', ''],
        ]);
        $memberShipServices->save((int) $id, $data);

        return $this->success($id ? 100001 : 100021);
    }

    /**
     * 删除
     *
     * @param $id
     * @param MemberShipServices $memberShipServices
     */
    public function delete($id, MemberShipServices $memberShipServices)
    {
        if (!$id) {
            return $this->fail(100026);
        }
        $res = $memberShipServices->delete((int) $id);

        return $this->success($res ? 100002 : 100008);
    }

    /**
     * 获取会员记录
     */
    public function member_record()
    {
        $where = $this->getMore([
            ['name', ""],
            ['add_time', ""],
            ['member_type', ""],
            ['pay_type', ""],
            ['page', 1],
            ['limit', 20],
        ]);
        $data = $this->service->getSearchRecordList($where);

        return $this->success($data);
    }

    /**
     * 会员权益
     */
    public function member_right()
    {
        /** @var MemberRightServices $memberRightServices */
        $memberRightServices = app(MemberRightServices::class);
        $data = $memberRightServices->getSearchList();

        return $this->success($data);
    }

    /**
     * 保存会员权益
     *
     * @param $id
     * @param MemberRightServices $memberRightServices
     */
    public function right_save($id, MemberRightServices $memberRightServices)
    {
        $data = $this->getMore([
            ['title', ''],
            ['show_title', ''],
            ['image', ''],
            ['right_type', ''],
            ['explain', ''],
            ['number', ''],
            ['sort', ''],
            ['status', ''],
        ]);
        $memberRightServices->save((int) $id, $data);

        return $this->success(400312);
    }

    /**
     * 会员卡激活冻结状态修改
     */
    public function set_status()
    {
        [$card_id, $status] = $this->getMore([
            ['card_id', 0],
            ['status', 0],
        ], true);
        $res = $this->service->setStatus($card_id, $status);
        if ($res) {
            return $this->success(100010);
        }

        return $this->success(100005);
    }

    /**
     * 付费会员类型启用/禁用
     */
    public function set_ship_status()
    {
        [$id, $is_del] = $this->getMore([
            ['id', 0],
            ['is_del', 0],
        ], true);
        /** @var MemberShipServices $memberShipServices */
        $memberShipServices = app(MemberShipServices::class);
        $res = $memberShipServices->setStatus($id, $is_del);
        if ($res) {
            return $this->success(100010);
        }

        return $this->success(100005);
    }
}
