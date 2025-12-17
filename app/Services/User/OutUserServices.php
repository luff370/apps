<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Dao\User\UserDao;
use App\Services\Service;
use App\Services\System\SystemUserLevelServices;
use App\Services\activity\coupon\StoreCouponIssueServices;

/**
 *
 * Class OutUserServices
 *
 * @package App\Services\User
 */
class OutUserServices extends Service
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
     * 用户列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getUserList(array $where): array
    {
        /** @var UserWechatuserServices $userWechatUser */
        $userWechatUser = app(UserWechatuserServices::class);
        $fields = 'u.uid, u.real_name, u.mark, u.nickname, u.avatar, u.phone, u.now_money, u.brokerage_price, u.integral, u.exp, u.sign_num, u.user_type,
        u.status, u.level, u.agent_level, u.spread_open, u.spread_uid, u.spread_time, u.user_type, u.is_promoter, u.pay_count, u.is_ever_level, u.is_money_level,
        u.overdue_time, u.add_time';
        [$list, $count] = $userWechatUser->getWhereUserList($where, $fields);
        if ($list) {
            $uids = array_column($list, 'uid');
            $levelName = app(SystemUserLevelServices::class)->getUsersLevel(array_unique(array_column($list, 'level')));
            $userLevel = app(UserLevelServices::class)->getUsersLevelInfo($uids);
            $spreadNames = $this->dao->getColumn([['uid', 'in', array_unique(array_column($list, 'spread_uid'))]], 'nickname', 'uid');
            foreach ($list as &$item) {
                $item['spread_uid_nickname'] = $item['spread_uid'] ? ($spreadNames[$item['spread_uid']] ?? '') . '/' . $item['spread_uid'] : '';
                //用户类型
                if ($item['user_type'] == 'routine') {
                    $item['user_type'] = '小程序';
                } else {
                    if ($item['user_type'] == 'wechat') {
                        $item['user_type'] = '公众号';
                    } else {
                        if ($item['user_type'] == 'h5') {
                            $item['user_type'] = 'H5';
                        } else {
                            if ($item['user_type'] == 'pc') {
                                $item['user_type'] = 'PC';
                            } else {
                                if ($item['user_type'] == 'app' || $item['user_type'] == 'apple') {
                                    $item['user_type'] = 'APP';
                                } else {
                                    $item['user_type'] = '其他';
                                }
                            }
                        }
                    }
                }

                //用户等级
                $item['level_name'] = "";
                $levelInfo = $userLevel[$item['uid']] ?? null;
                if ($levelInfo) {
                    if ($levelInfo['is_forever'] || time() < $levelInfo['valid_time']) {
                        $item['level_name'] = $levelName[$item['level']] ?? '';
                    }
                }
            }
        }

        return compact('list', 'count');
    }

    /**
     * 添加/修改用户
     *
     * @param int $uid
     * @param array $data
     *
     * @return int
     */
    public function saveUser(int $uid, array $data): int
    {
        if (empty($data['real_name'])) {
            throw new AdminException(400760);
        }
        if (empty($data['phone'])) {
            throw new AdminException(400132);
        }

        if (!check_phone($data['phone'])) {
            throw new AdminException(400252);
        }
        if ($uid < 1 && $this->count(['phone' => $data['phone'], 'is_del' => 0])) {
            throw new AdminException(400314);
        }

        if ($data['pwd']) {
            $data['pwd'] = md5($data['pwd']);
        } else {
            if ($uid < 1) {
                $data['pwd'] = md5(123456);
            } else {
                unset($data['pwd']);
            }
        }

        return \DB::transaction(function () use ($uid, $data) {
            if ($uid) {
                $userInfo = $this->dao->update($uid, $data);
            } else {
                if (trim($data['real_name']) != '') {
                    $data['nickname'] = $data['real_name'];
                } else {
                    $data['nickname'] = substr_replace($data['phone'], '****', 3, 4);
                }

                $data['avatar'] = sys_config('h5_avatar');
                $data['user_type'] = 'h5';
                $data['add_time'] = time();
                $userInfo = $this->dao->save($data);
                $uid = (int) $userInfo->uid;
            }
            if (!$userInfo) {
                throw new AdminException(100006);
            }

            /** @var UserServices $userServices */
            $userServices = app(UserServices::class);

            $level = (int) $data['level'];
            if ($level) {
                if (!$userServices->saveGiveLevel($uid, (int) $data['level'])) {
                    throw new AdminException(400219);
                }
            }

            return $uid;
        });
    }

    /**
     * 赠送(积分/余额/付费会员)
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function otherGive(int $id, array $data): bool
    {
        return \DB::transaction(function () use ($id, $data) {
            /** @var UserServices $userServices */
            $userServices = app(UserServices::class);

            $days = (int) $data['days'];
            $coupon = (int) $data['coupon'];
            unset($data['days'], $data['coupon']);
            if ($days > 0) {
                $userServices->saveGiveLevelTime($id, $days);
            }

            if ($coupon) {
                /** @var StoreCouponIssueServices $issueServices */
                $issueServices = app(StoreCouponIssueServices::class);
                $coupon = $issueServices->get($data['id']);
                if (!$coupon) {
                    throw new AdminException(100026);
                } else {
                    $coupon = $coupon->toArray();
                }
                $issueServices->setCoupon($coupon, [$id]);
            }

            $data['adminId'] = 0;
            $data['is_other'] = true;
            $data['money'] = (string) $data['money'];
            $data['integration'] = (string) $data['integration'];

            return $userServices->updateInfo($id, $data);
        });
    }
}
