<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\Task\TaskService;

class TaskController extends Controller
{
    public function __construct(TaskService $taskService)
    {
        $this->service = $taskService;
    }

    public function getStatus(Request $request)
    {
        // $taskId = $request->get('task_id');
        $taskType = $request->get('task_type');
        $linkId = $request->get('link_id');
        $userId = authUserId();

        $data = $this->service->getStatus($this->getAppId(), $taskType, $userId, $linkId);

        return $this->success($data);
    }

    public function completed(Request $request)
    {
        $taskId = $request->get('task_id');
        $linkId = $request->get('link_id');
        $userId = authUserId();

        $this->service->completed($taskId, $userId, $linkId);

        return $this->success();
    }
}
