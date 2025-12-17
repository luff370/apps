<?php

namespace App\Services\ChatAI;

use App\Services\Service;
use App\Models\AiTaskLog;
use App\Support\Utils\BaiduAI;
use App\Dao\ChatAI\AiTaskLogDao;

class AiTaskService extends Service
{
    public function __construct(AiTaskLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @throws \Exception
     */
    public function task($params)
    {
        $messages = [];
        if (!empty($params['dialogue_id'])) {
            $taskLogs = AiTaskLog::query()
                ->where('dialogue_id', $params['dialogue_id'])
                ->orderBy('id')
                ->get();

            foreach ($taskLogs as $taskLog) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $taskLog['input_content'],
                ];
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $taskLog['return_content'],
                ];
            }
        }

        // 调用百度AI
        $res = BaiduAI::run($params['input_content'], $messages);

        $params['task_id'] = $res['id'];
        $params['return_content'] = $res['result'];
        $params['prompt_tokens'] = $res['usage']['prompt_tokens'];
        $params['completion_tokens'] = $res['usage']['completion_tokens'];
        $params['total_tokens'] = $res['usage']['total_tokens'];

        $taskInfo = $this->dao->save($params);

        return ['id'=>$taskInfo['id'],'result'=>$res['result']];
    }
}
