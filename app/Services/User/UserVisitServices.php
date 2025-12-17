<?php

declare (strict_types = 1);

namespace App\Services\User;

use App\Services\Service;
use App\Dao\User\UserVisitDao;
use Illuminate\Support\Facades\Log;

/**
 *
 * Class UserVisitServices
 *
 * @package App\Services\User
 * @method count(array $where)
 * @method getDistinctCount(array $where, $field, ?bool $search = true)
 * @method sum(array $where, string $field)
 * @method getTrendData($time, $type, $timeType, $str)
 * @method getRegion($time, $channelType)
 * @method int groupCount(array $where, string $group = 'uid') 根据分组获取记录条数
 */
class UserVisitServices extends Service
{
    /**
     * UserVisitServices constructor.
     *
     * @param UserVisitDao $dao
     */
    public function __construct(UserVisitDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 登录后记录访问记录
     *
     * @param array|object $user
     *
     * @return mixed
     */
    public function loginSaveVisit($user)
    {
        try {
            $data = [
                'url' => '/pages/index/index',
                'uid' => $user['uid'] ?? 0,
                'ip' => request()->ip(),
                'add_time' => time(),
                'province' => $user['province'] ?? '',
                'channel_type' => $user['user_type'] ?? 'h5',
            ];
            if (!$data['uid']) {
                return false;
            }

            return $this->dao->save($data);
        } catch (\Throwable $e) {
            Log::error('登录记录访问日志错误，错误原因：' . $e->getMessage());
        }
    }
}
