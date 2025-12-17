<?php

namespace App\Support\Services\Workerman;

use App\Exceptions\AuthException;
use Workerman\Connection\TcpConnection;
use App\Services\System\Admin\AdminAuthServices;

class WorkermanHandle
{
    protected $service;

    public function __construct(WorkermanService &$service)
    {
        $this->service = &$service;
    }

    public function login(TcpConnection &$connection, array $res, Response $response)
    {
        if (!isset($res['data']) || !$token = $res['data']) {
            return $response->close([
                'msg' => '授权失败!',
            ]);
        }

        try {
            /** @var AdminAuthService $adminAuthService */
            $adminAuthService = app(AdminAuthServices::class);
            $authInfo = $adminAuthServices->parseToken($token);
        } catch (AuthException $e) {
            return $response->close([
                'msg' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
        }

        if (!$authInfo || !isset($authInfo['id'])) {
            return $response->close([
                'msg' => '授权失败!',
            ]);
        }

        $connection->adminInfo = $authInfo;
        $connection->adminId = $authInfo['id'];
        $this->service->setUser($connection);

        return $response->success();
    }
}
