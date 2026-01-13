<?php

namespace App\Support\Services\ReadTask;

use App\Support\Services\ReadTask\Contracts\ReadTaskChannelInterface;
use App\Support\Utils\Http;
use Illuminate\Http\Request;

abstract class AbstractReadTaskChannel implements ReadTaskChannelInterface
{
    protected string $ch;
    protected string $chUserKey;
    protected string $getReadUrl;

    public function getReadTask(string $openid): array
    {
        $params = [
            'openid'       => $openid,
            'ch'           => $this->ch,
            'ch_user_key'  => $this->chUserKey,
        ];

        $result = Http::getRequest($this->getReadUrl, $params);

        if (!$result) {
            throw new \Exception('请求三方失败');
        }

        return json_decode($result, true);
    }

    public function verifyCallback(array $data): bool
    {
        return !empty($data['ch_user_key'])
            && $data['ch_user_key'] === $this->chUserKey;
    }

    public function handleCallback(Request $request): void
    {
        $data = $request->all();

        if (!$this->verifyCallback($data)) {
            throw new \Exception('密钥校验失败');
        }

        $this->processCallback($data);
    }

    // 每个渠道自己实现
    abstract protected function processCallback(array $data): void;
}
