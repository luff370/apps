<?php

namespace App\Http\Controllers\Api;

use App\Support\Utils\Http;
use Illuminate\Http\Request;

class XiongfengAdController extends Controller
{
    // 获取任务平台链接
    public function getTaskDomain()
    {
        $url = config('xiongfeng.domain').'/api/channel/getTaskDomain';
        $param = [
            'cid' => config('xiongfeng.cid'),
        ];
        $result = Http::postJson($url, json_encode($param));
        if (!$result) {
            return $this->fail('请求失败');
        }

        $data = json_decode($result, true);
        if (empty($data['data'])) {
            return $this->fail('获取任务失败' . $result);
        }

        return $this->success($data['data']);
    }

    // 获取广告投放链接(返回量专用)
    public function getReadDomain()
    {
        $url = config('xiongfeng.domain').'/api/channel/getReadDomain';
        $param = [
            'cid' => config('xiongfeng.cid'),
        ];
        $result = Http::getRequest($url, $param);
        if (!$result) {
            return $this->fail('请求失败');
        }

        $data = json_decode($result, true);

        return $this->success($data);
    }

    public function completedCallback(Request $request)
    {
        $data = $request->all();
        logger()->info('completedTaskCallback', $data);

        if (empty($dada['uid']) || empty($dada['requestId']) || empty($dada['requestIp'])) {
            return $this->fail('请求参数缺失');
        }

        echo 'success';
    }
}
