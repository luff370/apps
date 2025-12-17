<?php

namespace App\Services\User\Member;

use App\Services\Service;
use App\Dao\User\MemberShipDao;
use App\Exceptions\AdminException;

/**
 * Class MemberShipServices
 *
 * @package App\Services\User
 */
class MemberShipServices extends Service
{
    public function __construct(MemberShipDao $memberShipDao)
    {
        $this->dao = $memberShipDao;
    }

    /**后台获取会员类型
     *
     * @param array $where
     *
     * @return array
     */
    public function getSearchList(array $where = [])
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getSearchList($where, $page, $limit);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**获取会员卡api接口
     *
     * @return mixed
     */
    public function getApiList(array $where)
    {
        return $this->dao->getApiList($where);
    }

    /** 卡类型编辑保存
     *
     * @param int $id
     * @param array $data
     */
    public function save(int $id, array $data)
    {
        if (!$data['title']) {
            throw new AdminException(400641);
        }
        if (!$data['type']) {
            throw new AdminException(400642);
        }
        if ($data['type'] == "ever") {
            $data['vip_day'] = -1;
        } else {
            if (!$data['vip_day']) {
                throw new AdminException(400643);
            }
            if ($data['vip_day'] < 0) {
                throw new AdminException(400644);
            }
        }
        if ($data['type'] == "free") {
            $data['pre_price'] = 0.00;
        } else {
            if ($data['pre_price'] == 0 || $data['price'] == 0) {
                throw new AdminException(400645);
            }
        }
        if ($data['pre_price'] < 0 || $data['price'] < 0) {
            throw new AdminException(400646);
        }
        if ($data['pre_price'] > $data['price']) {
            throw new AdminException(400647);
        }
        if ($id) {
            return $this->dao->update($id, $data);
        } else {
            return $this->dao->save($data);
        }
    }

    /**获取卡会员天数
     *
     * @param array $where
     *
     * @return mixed
     */
    public function getVipDay(array $where)
    {
        return $this->dao->value($where, 'vip_day');
    }

    /**
     * 修改会员类型状态
     *
     * @param $id
     * @param $is_del
     *
     * @return bool
     */
    public function setStatus($id, $is_del)
    {
        $res = $this->dao->update($id, ['is_del' => $is_del]);
        if ($res) {
            return true;
        }

        return false;
    }
}
