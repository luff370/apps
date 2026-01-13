<?php

namespace App\Support\Services\ReadTask;

use App\Support\Services\ReadTask\Contracts\ReadTaskChannelInterface;
use App\Support\Utils\Http;
use Illuminate\Http\Request;

abstract class AbstractReadTaskChannel implements ReadTaskChannelInterface
{
    protected string $ch;
    protected string $getReadUrl;

    public function getReadTask(string $openid): array
    {
        $params = [
            'openid' => $openid,
            'ch' => $this->ch,
            'ch_user_key' => $openid,
        ];

        $result = Http::getRequest($this->getReadUrl, $params);

        if (!$result) {
            throw new \Exception('请求三方失败');
        }

        return json_decode($result, true);
    }

    public function verifyCallback(array $data): bool
    {
        return true;
    }

    public function handleCallback(Request $request): void
    {
        $data = $request->all();
        logger()->info("readTask 异步回调", $data);

        if (!$this->verifyCallback($data)) {
            throw new \Exception('密钥校验失败');
        }

        $this->processCallback($data);
    }

    // 每个渠道自己实现
    abstract protected function processCallback(array $data): void;
}
