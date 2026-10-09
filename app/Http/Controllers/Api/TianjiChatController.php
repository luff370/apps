<?php

namespace App\Http\Controllers\Api;

use App\Services\Tianji\TianjiChatException;
use App\Services\Tianji\TianjiChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TianjiChatController extends Controller
{
    /** 流式发送一轮文墨对话。参数错误先返回 JSON，生成过程用 SSE 推送。 */
    public function message(Request $request, TianjiChatService $service)
    {
        $input = [
            'user_id' => authUserId(),
            'app_id' => $this->appId(),
            'session_id' => $request->input('session_id'),
            'new_session' => $request->boolean('new_session'),
            'target_type' => $request->input('target_type'),
            'target_key' => $request->input('target_key'),
            'content' => $request->input('content'),
            'report' => $request->input('report'),
        ];

        try {
            $turn = $service->prepare($input);
        } catch (TianjiChatException $e) {
            return $this->fail($e->getMessage(), null, $e->status);
        }

        return $this->eventStream(function () use ($service, $turn) {
            try {
                $service->play($turn, function (string $event, array $data) {
                    $this->writeEvent($event, $data);
                });
            } catch (TianjiChatException $e) {
                $this->writeEvent('error', ['status' => $e->status, 'msg' => $e->getMessage()]);
            }
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

    /** 关闭输出缓冲，按 SSE 持续写出。 */
    private function eventStream(callable $callback): StreamedResponse
    {
        return response()->stream(function () use ($callback) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                if (@ob_end_flush() === false) {
                    break;
                }
            }
            $callback();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /** 写出一条 SSE 事件。data 是一行 JSON。 */
    private function writeEvent(string $event, array $data): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}
