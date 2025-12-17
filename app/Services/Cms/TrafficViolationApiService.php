<?php

namespace App\Services\Cms;

use Carbon\Carbon;
use App\Services\Service;
use App\Exceptions\ApiException;
use App\Dao\Content\TrafficViolationContentDao;

class TrafficViolationApiService extends Service
{
    public function __construct(TrafficViolationContentDao $dao)
    {
        $this->dao = $dao;
    }

    public function tidyListData($list): array
    {
        $data = [];
        foreach ($list as $item) {
            $data[] = [
                'id' => $item['id'],
                'nickname' => '用户' . $item['user_id'],
                'type' => $item['type'],
                'city' => $item['city'],
                'images' => $item['images'],
                'show_time' => $item['show_time'],
                'description' => $item['description'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * 举报记录详情
     *
     * @param $id
     * @param int $userId
     *
     * @return array
     */
    public function details($id, $userId = 0): array
    {
        $data = $this->dao->newQuery()->findOrFail($id);

        $info = [
            'id' => $data['id'],
            'type' => $data['type'],
            'car_type' => $data['car_type'],
            'address' => $data['address'],
            'images' => $data['images'],
            'description' => $data['description'],
            'show_time' => Carbon::parse($data['show_time'])->format('Y年m月d日 H:i:s'),
            'submit_time' => Carbon::parse($data['created_at'])->format('Y年m月d日 H:i:s'),
            'violation_time' => Carbon::parse($data['violation_time'])->format('Y年m月d日 H:i:s'),
            'province_code' => $data['province_code'],
            'license_plate_number' => $data['license_plate_number'],
        ];

        if ($userId == $data['user_id']) {
            $info['audit_status'] = $data['audit_status'];
            $info['is_get_reward'] = $data['is_get_reward'];
            $info['reward_count'] = $data['reward_count'];

            // 未读消息 标记为已读
            if ($data['notification_status'] == 1) {
                $data['notification_status'] = 2;
                $data['is_top'] = 0;
                $data->save();
            }
        } else {
            $info['license_plate_number'] = mb_substr($data['license_plate_number'], 0, 2) . '****' . substr($data['license_plate_number'], -1);
        }

        return $info;
    }

    /**
     * 获取用户举报记录(30天内)
     *
     * @param $userId
     * @param null $audit_status
     *
     * @return array
     */
    public function getUserRecords($userId, $audit_status = null): array
    {
        $list = $this->dao->newQuery()
            ->select(['id', 'type', 'city', 'images', 'created_at as show_time', 'audit_status', 'notification_status', 'reward_count', 'is_get_reward', 'description'])
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30)->toDateString())
            ->when($audit_status !== null, function ($query) use ($audit_status) {
                $query->where('audit_status', $audit_status);
            })
            ->orderBy('is_top', 'desc')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        $total = $list->count();
        $audit_unknown = $list->where('audit_status', 0)->count();
        $audit_success = $list->where('audit_status', 1)->count();
        $audit_success_news = $list->where('audit_status', 1)->where('notification_status', 1)->count();
        $audit_fail = $list->where('audit_status', 2)->count();
        $audit_fail_news = $list->where('audit_status', 2)->where('notification_status', 1)->count();

        return [
            'list' => $list,
            'total' => $total,
            'audit_unknown' => $audit_unknown,
            'audit_success' => $audit_success,
            'audit_success_news' => $audit_success_news,
            'audit_fail' => $audit_fail,
            'audit_fail_news' => $audit_fail_news,
        ];
    }

    /**
     * 领取奖励
     *
     * @throws \App\Exceptions\ApiException
     * @throws \Throwable
     */
    public function getRewards($id, $userId): bool
    {
        try {
            \DB::beginTransaction();

            $info = $this->dao->newQuery()
                ->where('id', $id)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($info['audit_status'] != 1) {
                throw new ApiException('该举报未审核通过，不能领取奖励');
            }

            if ($info['is_get_reward'] == 1) {
                throw new ApiException('奖励已领取');
            }

            $this->dao->update($id, ['is_get_reward' => 1]);

            $userIntegral = $this->userService()->value(['id' => $userId], 'integral');
            $giveIntegral = config('traffic.reward_count');

            // 增加用户积分余额
            $this->userService()->bcInc($userId, 'integral', (string) $giveIntegral);
            // 增加用户积分赠送记录
            $this->userBillService()->income('report_reward_give_energy', $this->getAppId(), $userId, (int) $giveIntegral, $userIntegral + $giveIntegral, $info['id']);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw new ApiException($e->getMessage());
        }

        return true;
    }
}
