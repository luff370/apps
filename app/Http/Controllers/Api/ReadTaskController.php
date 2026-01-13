<?php

namespace App\Http\Controllers\Api;

use App\Support\Services\ReadTask\ChannelFactory;
use App\Support\Utils\Http;
use Illuminate\Http\Request;

const CH = 'uu03qqd';
const CH_USER_KEY = 'zZ34i6FN6bTigUmS5eMbmNiR';

class ReadTaskController extends Controller
{

    /*public function getReadTask(Request $request)
    {
        $ch = $request->get('ch', 'uu03qqd');

        try {
            $channel = ChannelFactory::make($ch);
            $data = $channel->getReadTask($this->getUuid());
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }*/

    /*public function completedTaskCallback(Request $request)
    {
        try {
            $ch = $request->get('ch');
            $channel = ChannelFactory::make($ch);
            $channel->handleCallback($request);
            return response('success');
        } catch (\Throwable $e) {
            logger()->error('callback error', ['msg' => $e->getMessage()]);
            return $this->fail($e->getMessage());
        }
    }*/

    public function getReadTask()
    {
        $url = 'http://47.57.244.93/read_channel_api/get_read_url';
        $param = [
            'openid' => $this->getUuid(),
            'ch' => CH,
            'ch_user_key' => CH_USER_KEY,
        ];
        $result = Http::getRequest($url, $param);
        if (!$result) {
            return $this->fail('请求失败');
        }

        $data = json_decode($result, true);
        // if (empty($data['data'])) {
        //     return $this->fail('获取任务失败' . $result);
        // }

        return $this->success($data);
    }

    public function completedTaskCallback(Request $request)
    {
        $data = $request->all();
        logger()->info('completedTaskCallback', $data);

        if (empty($data['ch']) || empty($data['ch_user_key']) || empty($data['date'])) {
            return $this->fail('请求参数缺失');
        }

        if ($data['ch_user_key'] != CH_USER_KEY) {
            return $this->fail('密匙效验失败');
        }

        $date = $data['date'];
        $readTimes = $data['read_times'] ?? 0;

        echo 'success';
    }
}
