<?php

namespace App\Http\Middleware;

use Closure;
use App\Utils\Crypto;
use Illuminate\Routing\Controllers\Middleware;

class EncryptDecryptApi extends Middleware
{
    public function handle($request, Closure $next)
    {
        // 判断是否开启
        if (!Crypto::isOpen()){
            return $next($request);
        }

        // 1️⃣ 解密请求
        $data = $request->input('data');
        $sign = $request->input('sign');

        if ($data && $sign) {
            if (!Crypto::verify($data, $sign)) {
                return response()->json(['error' => '签名验证失败'], 400);
            }

            $decrypted = Crypto::decrypt($data);
            $request->merge($decrypted);
        }

        // 2️⃣ 执行业务逻辑
        $response = $next($request);

        // 3️⃣ 对返回内容加密
        if ($response->getStatusCode() === 200 && $this->isJsonResponse($response)) {
            $original = json_decode($response->getContent(), true);

            // 不加密错误信息
            if (isset($original['error'])) {
                return $response;
            }

            $encrypted = Crypto::encrypt($original);
            $sign = Crypto::sign($encrypted);

            return response()->json([
                'data' => $encrypted,
                'sign' => $sign,
            ]);
        }

        return $response;
    }

    protected function isJsonResponse($response)
    {
        return str_contains($response->headers->get('Content-Type'), 'application/json');
    }
}


