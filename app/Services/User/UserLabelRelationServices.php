<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Exceptions\AdminException;
use App\Dao\User\UserLabelRelationDao;

/**
 *
 * Class UserLabelRelationServices
 *
 * @package App\Services\User
 * @method getColumn(array $where, string $field, string $key = '') 获取某个字段数组
 * @method saveAll(array $data) 批量保存数据
 */
class UserLabelRelationServices extends Service
{
    /**
     * UserLabelRelationServices constructor.
     *
     * @param UserLabelRelationDao $dao
     */
    public function __construct(UserLabelRelationDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取某个用户标签ids
     *
     * @param int $uid
     *
     * @return array
     */
    public function getUserLabels(int $uid)
    {
        return $this->dao->getColumn(['uid' => $uid], 'label_id', '');
    }

    /**
     * 用户设置标签
     *
     * @param int $uid
     * @param array $labels
     */
    public function setUserLable($uids, array $labels)
    {
        if (!is_array($uids)) {
            $uids = [$uids];
        }
        $re = $this->dao->delete([['uid', 'in', $uids]]);
        if (!count($labels)) {
            return true;
        }
        if ($re === false) {
            throw new AdminException(400667);
        }
        /** @var UserServices $userServices */
        $userServices = app(UserServices::class);
        $data = [];
        foreach ($uids as $uid) {
            foreach ($labels as $label) {
                $data[] = ['uid' => $uid, 'label_id' => $label];
            }
            $userServices->update(['uid' => $uid], ['label_ids' => implode(',', $labels)]);
        }
        if ($data) {
            if (!$this->dao->saveAll($data)) {
                throw new AdminException(400668);
            }
        }

        return true;
    }

    /**
     * 取消用户标签
     *
     * @param int $uid
     * @param array $labels
     *
     * @return mixed
     */
    public function unUserLabel(int $uid, array $labels)
    {
        if (!count($labels)) {
            return true;
        }
        $this->dao->delete([
            ['uid', '=', $uid],
            ['label_id', 'in', $labels],
        ]);

        return true;
    }

    /**
     * 获取用户标签
     *
     * @param array $uids
     *
     * @return array
     */
    public function getUserLabelList(array $uids)
    {
        return $this->dao->getLabelList($uids);
    }
}
