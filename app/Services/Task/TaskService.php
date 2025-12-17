<?php

namespace App\Services\Task;

use App\Models\Task;
use App\Models\TaskLog;
use App\Dao\Task\TaskDao;
use App\Models\UserWithdrawal;
use App\Services\Service;
use App\Exceptions\AdminException;
use App\Exceptions\RequestException;
use App\Support\Services\FormBuilder as Form;

/**
 * Class TaskService
 */
class TaskService extends Service
{
    /**
     * TaskService constructor.
     */
    public function __construct(TaskDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        foreach ($list as &$item) {
            $item['app_name'] = '';
            $item['type_name'] = trans('task.type_map')[$item['type']] ?? '';
            $item['ad_name'] = '';
            $item['frequency'] = trans('task.frequency_map')[$item['frequency']] ?? '';
            $item['status_name'] = '';
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm()
    {
        return create_form('添加', $this->createUpdateForm(), url('/admin/task/task'));
    }

    /**
     * 编辑表单获取
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/task/task/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $f[] = Form::select('app_id', '应用', $info['app_id'] ?? '')->options()->filterable(true)->requiredNum();
        $f[] = Form::text('name', '任务名称', $info['name'] ?? '');
        $f[] = Form::select('type', '任务类别', $info['type'] ?? '')->options($this->toFormSelect(trans('task.type_map')))->filterable(true)->requiredNum();
        $f[] = Form::text('ad_id', '广告', $info['ad_id'] ?? '');
        $f[] = Form::text('frequency', '任务频次', $info['frequency'] ?? '');
        $f[] = Form::number('count', '总次数', $info['count'] ?? '');
        $f[] = Form::radio('status', '总次数', $info['status'] ?? '1')->options();

        return $f;
    }

    /**
     * @throws RequestException
     */
    public function getStatus($appId, $taskType, $userId, $linkId = 0): array
    {
        $task = Task::query()->where('app_id', $appId)->where('type', $taskType)->first();
        if (!$task) {
            throw new RequestException('任务信息获取失败');
        }

        $query = TaskLog::query()
            ->where('completed_status', 0)
            ->where('task_id', $task['id'])
            ->where('user_id', $userId);

        switch ($task['frequency']) {
            case 'total':
                break;
            case 'year':
                $query->where('created_at', '>=', today()->subYear()->toDateString());
                break;
            case 'month':
                $query->where('created_at', '>=', today()->subMonth()->toDateString());
                break;
            case 'day':
                $query->where('created_at', '>=', today()->toDateString());
                break;
        }

        $userTask = $query->orderBy('created_at',)->first();

        $completedCount = 0;
        if ($userTask) {
            $completedCount = $userTask->completed_count ?? 0;
        }

        return [
            'task_id' => $task['id'],
            'task_name' => $task['name'],
            'task_type' => $task['type'],
            'total_count' => $task['count'],
            'completed_count' => $completedCount,
        ];
    }

    public function completed($taskId, $userId, $linkId = 0)
    {
        $task = Task::query()->where('id', $taskId)->first();
        $userTask = TaskLog::query()->where('task_id', $taskId)->where('user_id', $userId)
            ->where('completed_status', 0)
            ->first();

        if ($userTask) {
            $userTask->completed_count += 1;
            $userTask->completed_status = $userTask->completed_count >= $task['count'] ? 1 : 0;
            $userTask->save();
        } else {
            $userTask = TaskLog::query()->create([
                'app_id' => $this->getAppId(),
                'task_id' => $taskId,
                'link_id' => $linkId,
                'user_id' => $userId,
                'total_count' => $task['count'] ?? 0,
                'completed_count' => 1,
                'completed_status' => $task['count'] == 1 ? 1 : 0,
            ]);
        }

        $completedStatus = $userTask->completed_status ?? 0;
        $redEnvelopeAmount = 0;

        switch ($task['type']) {
            case 'incentive_ad_give_red_envelope':
                $res = $this->redEnvelopeService()->getRedEnvelope('incentive_ad_give_red_envelope', $userId);
                $redEnvelopeAmount = $res['red_envelope_amount'];
                // 完成任务，重置提现次数
                if ($completedStatus == 1) {
                    UserWithdrawal::query()->where('user_id', $userId)
                        ->where('apply_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
                        ->where('today_withdrawal_count_mark', 1)
                        ->update(['today_withdrawal_count_mark' => 2]);
                }

                break;
            case 'incentive_ad_for_direct_withdraw':
                // 完成任务，直接提现
                if ($completedStatus == 1) {
                    $res = $this->redEnvelopeService()->getRedEnvelope('incentive_ad_for_direct_withdraw', $userId);
                    $redEnvelopeAmount = $res['red_envelope_amount'];

                    // 发起转账
                    $this->transferOrderService()->transferToUserByAlipayAccount($this->getAppId(), $userId, $redEnvelopeAmount);
                }
                break;

        }

        return [
            'task_id' => $task['id'],
            'task_name' => $task['name'],
            'task_type' => $task['type'],
            'total_count' => $task['count'],
            'completed_count' => $userTask->completed_count ?? 0,
            'completed_status' => $completedStatus,
            'red_envelope_amount' => $redEnvelopeAmount,
        ];
    }
}
