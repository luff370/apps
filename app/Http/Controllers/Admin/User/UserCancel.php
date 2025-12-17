<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\Controller;
use App\Services\User\UserCancelServices;

class UserCancel extends Controller
{
    /**
     * UserCancel constructor.
     *
     * @param UserCancelServices $services
     */
    public function __construct(UserCancelServices $services)
    {
        $this->service = $services;
    }

    /**
     * 获取注销列表
     */
    public function getCancelList()
    {
        $where = $this->getMore([
            ['status', 0],
            ['keywords', ''],
        ]);
        $data = $this->service->getCancelList($where);

        return $this->success($data);
    }

    /**
     * 备注
     */
    public function setMark()
    {
        [$id, $mark] = $this->getMore([
            ['id', 0],
            ['mark', ''],
        ], true);
        $this->service->serMark($id, $mark);

        return $this->success(100024);
    }

    public function agreeCancel($id)
    {
        return $this->success(400319);
    }

    public function refuseCancel($id)
    {
        return $this->success(400320);
    }
}
