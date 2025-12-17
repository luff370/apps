<?php

namespace App\Services\User\Member;

use App\Services\Service;
use App\Dao\User\MemberRightDao;
use App\Exceptions\AdminException;

/**
 * Class MemberRightServices
 *
 * @package App\Services\User
 */
class MemberRightServices extends Service
{
    /**
     * MemberCardServices constructor.
     *
     * @param MemberRightDao $memberCardDao
     */
    public function __construct(MemberRightDao $memberRightDao)
    {
        $this->dao = $memberRightDao;
    }

    /**
     * @param array $where
     *
     * @return array
     */
    public function getSearchList(array $where = [])
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getSearchList($where, $page, $limit);
        foreach ($list as &$item) {
            $item['image'] = set_file_url($item['image']);
        }
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 编辑保存
     *
     * @param int $id
     * @param array $data
     */
    public function save(int $id, array $data)
    {
        if (!$data['right_type']) {
            throw new AdminException(400630);
        }
        if (!$id) {
            throw new AdminException(100100);
        }
        if (!$data['title'] || !$data['show_title']) {
            throw new AdminException(400631);
        }
        if (!$data['image']) {
            throw new AdminException(400632);
        }
        if (mb_strlen($data['show_title']) > 6) {
            throw new AdminException(400755);
        }
        if (mb_strlen($data['explain']) > 8) {
            throw new AdminException(400752);
        }
        switch ($data['right_type']) {
            case "integral":
                if (!$data['number']) {
                    throw new AdminException(400633);
                }
                if ($data['number'] < 0) {
                    throw new AdminException(400634);
                }
                $save['number'] = abs($data['number']);
                break;
            case "express" :
                if (!$data['number']) {
                    throw new AdminException(400635);
                }
                if ($data['number'] < 0) {
                    throw new AdminException(400636);
                }
                $save['number'] = abs($data['number']);
                break;
            case "sign" :
                if (!$data['number']) {
                    throw new AdminException(400637);
                }
                if ($data['number'] < 0) {
                    throw new AdminException(400638);
                }
                $save['number'] = abs($data['number']);
                break;
            case "offline" :
                if (!$data['number']) {
                    throw new AdminException(400639);
                }
                if ($data['number'] < 0) {
                    throw new AdminException(400640);
                }
                $save['number'] = abs($data['number']);
        }
        $save['show_title'] = $data['show_title'];
        $save['image'] = $data['image'];
        $save['status'] = $data['status'];
        $save['sort'] = $data['sort'];

        //TODO $save没有使用
        return $this->dao->update($id, $data);
    }

    /**
     * 获取单条信息
     *
     * @param array $where
     *
     * @return array|bool|\Illuminate\Database\Eloquent\Model|null
     */
    public function getOne(array $where)
    {
        if (!$where) {
            return false;
        }

        return $this->dao->getOne($where);
    }

    /**
     * 查看某权益是否开启
     *
     * @param $rightType
     *
     * @return bool
     */
    public function getMemberRightStatus($rightType)
    {
        if (!$rightType) {
            return false;
        }
        $status = $this->dao->value(['right_type' => $rightType], 'status');
        if ($status) {
            return true;
        }

        return false;
    }
}
