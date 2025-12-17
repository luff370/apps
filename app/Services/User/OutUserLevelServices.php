<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserLevelDao;
use App\Services\System\SystemUserLevelServices;

/**
 * 用户等级
 * Class OutUserLevelServices
 *
 * @package App\Services\User
 */
class OutUserLevelServices extends Service
{
    /**
     * OutUserLevelServices constructor.
     *
     * @param UserLevelDao $dao
     */
    public function __construct(UserLevelDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 会员列表
     *
     * @param array $where
     *
     * @return array
     */
    public function levelList(array $where): array
    {
        /** @var SystemUserLevelServices $systemLevelServices */
        $systemLevelServices = app(SystemUserLevelServices::class);
        $field = 'id, name, grade, discount, image, icon, explain, exp_num, is_show, add_time';

        return $systemLevelServices->getLevelList($where, $field);
    }
}
