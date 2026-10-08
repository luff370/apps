<?php

namespace App\Http\Controllers\Api;

use App\Services\Tianji\TianjiChatException;
use App\Services\Tianji\TianjiChatService;
use Illuminate\Http\Request;

class TianjiChatController extends Controller
{
    /** 发送一轮天纪对话。同一用户和目标会续接上下文，资料对话会记住计算报告。 */
    public function message(Request $request, TianjiChatService $service)
    {
        return $this->respond(function () use ($request, $service) {
            return $service->send([
                'user_id' => authUserId(),
                'app_id' => $this->appId(),
                'session_id' => $request->input('session_id'),
                'new_session' => $request->boolean('new_session'),
                'target_type' => $request->input('target_type'),
                'target_key' => $request->input('target_key'),
                'content' => $request->input('content'),
                'report' => $request->input('report'),
            ]);
        });
    }

    /** 列出当前用户最近的天纪对话。 */
    public function sessions(TianjiChatService $service)
    {
        return $this->respond(fn () => $service->sessions(authUserId(), $this->appId()));
    }

    /** 读取一条天纪对话的历史消息和已保存报告。 */
    public function history(Request $request, TianjiChatService $service)
    {
        return $this->respond(function () use ($request, $service) {
            return $service->history(authUserId(), $this->appId(), (int) $request->input('session_id'));
        });
    }

    /** 成功时返回 data，天纪业务错误返回统一失败结构。 */
    private function respond(callable $callback)
    {
        try {
            return $this->success($callback());
        } catch (TianjiChatException $e) {
            return $this->fail($e->getMessage(), null, $e->status);
        }
    }

    /** 从 App-Id 或包名映射取出应用 ID。 */
    private function appId(): int
    {
        $appId = $this->getAppId();

        return is_numeric($appId) ? (int) $appId : 0;
    }
}
