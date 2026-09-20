<?php

namespace App\Http\Controllers\Admin\User;

use App\Exceptions\AdminException;
use App\Http\Controllers\Admin\Controller;
use App\Services\User\AccountDeletionService;

class UserDeletionRequestController extends Controller
{
    public function __construct(AccountDeletionService $service)
    {
        $this->service = $service;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $filter = $this->getMore([
            ['app_id', ''],
            ['status', ''],
            ['keyword', ''],
            ['time', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc'], ['user']);

        return $this->success($data);
    }

    public function process($id): \Illuminate\Http\JsonResponse
    {
        $data = $this->getMore([
            ['user_id', 0],
            ['remark', ''],
        ]);

        try {
            $this->service->processRequest((int) $id, (int) $data['user_id'], (string) $data['remark']);
        } catch (AdminException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success('处理成功');
    }
}
