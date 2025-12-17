<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\User\UserWithdrawServices;

class UserWithdrawalController extends Controller
{
    public function __construct(UserWithdrawServices $services)
    {
        $this->service = $services;
    }

    public function products()
    {
        $data = $this->service->userWithdrawalProducts($this->getAppId());

        return $this->success($data);

    }

    /**
     * 用户提现申请
     *
     * @throws \Throwable
     * @throws \App\Exceptions\ApiException
     */
    public function application(Request $request)
    {
        $userId = authUserId();
        $account = $request->get('account');
        $accountName = $request->get('payee_name');
        $accountType = $request->get('method');
        $productId = $request->get('product_id');
        logger()->info('--------'.$productId,$request->all());

        $this->service->withdrawalApplication($userId, $account, $accountName, $accountType, $productId);

        return $this->success(null, '提交成功');
    }

    /**
     * 用户提现记录
     */
    public function list()
    {
        $data = $this->service->userWithdrawalList(authUserId());

        return $this->success($data);
    }
}
