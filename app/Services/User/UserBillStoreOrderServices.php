<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserBillStoreOrderDao;

/**
 *
 * Class UserBillStoreOrderServices
 *
 * @package App\Services\User
 */
class UserBillStoreOrderServices extends Service
{
    /**
     * UserBillStoreOrderServices constructor.
     *
     * @param UserBillStoreOrderDao $dao
     */
    public function __construct(UserBillStoreOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * TODO 获取用户记录 按月查找
     *
     * @param $uid $uid  用户编号
     * @param int $page $page 分页起始值
     * @param int $limit $limit 查询条数
     * @param string $category $category 记录类型
     * @param string $type $type 记录分类
     *
     * @return mixed
     */
    public function getRecordList($uid, $uids, $category = 'now_money', $type = 'brokerage')
    {
        $where = $orWhere1 = $orWhere2 = [];
        $where['b.category'] = $category;
        $where['o.refund_status'] = 0;
        $where['b.take'] = 0;
        $field = "FROM_UNIXTIME(b.add_time, '%Y-%m') as time";

        $orWhere1 = [
            ['b.uid', '=', $uid],
            ['b.type', '=', $type],
        ];
        $orWhere2 = [
            ['b.uid', 'IN', $uids],
            ['b.type', '=', 'pay_money'],
        ];
        [$page, $limit] = $this->getPageValue();

        return $this->dao->getListBygroupBy($where, [$orWhere1, $orWhere2], $field, 'time', $page, $limit);
    }

    /**
     * 获取订单返佣记录总数
     *
     * @param $uid
     * @param $uids
     * @param string $category
     * @param string $type
     *
     * @return mixed
     */
    public function getRecordOrderCount($uid, $uids, $category = 'now_money', $type = 'brokerage')
    {
        $where = $orWhere1 = $orWhere2 = [];
        $where['b.category'] = $category;
        $where['o.refund_status'] = 0;
        $where['b.take'] = 0;

        $orWhere1 = [
            ['b.uid', '=', $uid],
            ['b.type', '=', $type],
        ];
        $orWhere2 = [
            ['b.uid', 'IN', $uids],
            ['b.type', '=', 'pay_money'],
        ];

        return $this->dao->getListCount($where, [$orWhere1, $orWhere2]);
    }

    /**
     * TODO 获取订单返佣记录
     *
     * @param $uid
     * @param int $addTime
     * @param string $category
     * @param string $type
     *
     * @return mixed
     */
    public function getRecordOrderListDraw($uid, $uids, $addTime = [], $category = 'now_money', $type = 'brokerage')
    {
        if (!$addTime) {
            return [];
        }
        $where = $orWhere1 = $orWhere2 = [];
        $where['b.category'] = $category;
        $where['o.refund_status'] = 0;
        $where['b.take'] = 0;

        $orWhere1 = [
            ['b.uid', '=', $uid],
            ['b.type', '=', $type],
        ];
        $orWhere2 = [
            ['b.uid', 'IN', $uids],
            ['b.type', '=', 'pay_money'],
        ];
        $field = "o.id,o.uid,o.order_id,FROM_UNIXTIME(b.add_time, '%Y-%m-%d %H:%i') as time,b.number,b.type,FROM_UNIXTIME(b.add_time, '%Y-%m') as time_key";

        return $this->dao->getList($where, [$orWhere1, $orWhere2], $addTime, $field, 0, 0);
    }
}
