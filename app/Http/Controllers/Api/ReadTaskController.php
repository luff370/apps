<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Support\Services\ReadTask\ChannelFactory;

class ReadTaskController extends Controller
{
    public function getReadTask(Request $request)
    {
        $ch = $request->get('ch', 'uu03qqd');

        try {
            $channel = ChannelFactory::make($ch);
            $data = $channel->getReadTask($this->getUuid());
            if (isset($data['code']) && ($data['code'] != 0 && $data['code'] != 200)) {
                logger()->error('getReadTask error', $data);

                return $this->fail($data['msg'] ?? '获取失败', $data);
            }

            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function completedTaskCallback(Request $request, $ch)
    {
        try {
            $channel = ChannelFactory::make($ch);
            $channel->handleCallback($request);

            return response('success');
        } catch (\Throwable $e) {
            logger()->error('callback error', ['msg' => $e->getMessage()]);

            return $this->fail($e->getMessage());
        }
    }
}
