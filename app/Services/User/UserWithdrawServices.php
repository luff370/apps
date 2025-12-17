<?php

namespace App\Services\User;

use Carbon\Carbon;
use App\Models\User;
use App\Services\Service;
use App\Models\MemberProduct;
use App\Models\UserWithdrawal;
use App\Exceptions\ApiException;
use App\Dao\User\UserWithdrawDao;
use App\Exceptions\AdminException;
use App\Exceptions\RequestException;
use App\Support\Services\FormBuilder;
use App\Services\Wechat\WechatUserServices;

/**
 * 用户提现service
 * Class UserWithdrawServices
 */
class UserWithdrawServices extends Service
{
    /**
     * form表单创建
     *
     * @var FormBuilder
     */
    protected $builder;

    /**
     * StoreBrandServices constructor.
     */
    public function __construct(UserWithdrawDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    public function userWithdrawalProducts($appId)
    {
        return MemberProduct::query()
            ->select(['id', 'name', 'label', 'price', 'use_integral', 'buy_count', 'keyword', 'sales'])
            ->where('type', 'withdrawal')
            ->where('app_id', $appId)
            ->where('is_enable', 1)
            ->orderBy('sort', 'desc')
            ->get();
    }

    public function userWithdrawalList($userId)
    {
        $filter = [
            'user_id' => $userId,
            'app_id' => $this->getAppId(),
        ];
        [$page, $limit] = $this->getPageValue();
        $count = $this->dao->count($filter);
        $list = [];
        if ($count > 0) {
            $list = $this->dao->getAll($filter, ['*'], ['id' => 'desc'], [], $limit, $page)->toArray();

            foreach ($list as &$item) {
                $item['apply_time'] = Carbon::parse($item['apply_time'])->format('Y年m月d日 H:i');
            }
        }

        return compact('list', 'count');
    }

    /**
     * 提现申请
     *
     * @throws \Throwable
     * @throws \App\Exceptions\ApiException
     */
    public function withdrawalApplication($userId, $account, $accountName, $accountType, $productId)
    {
        $appId = $this->getAppId();
        $productInfo = MemberProduct::query()->find($productId);
        if (!$productInfo) {
            throw new RequestException('提现产品不存在或已下架');
        }

        $userInfo = User::query()->find($userId);
        if (!$userInfo) {
            throw new RequestException('用户不存在');
        }

        // 检测提现产品是否限制使用次数
        if ($productInfo['buy_count'] > 0) {
            $withdrawalCount = UserWithdrawal::query()
                ->where('product_id', $productId)
                ->where('user_id', $userId)
                ->whereIn('audit_status', [0, 1])
                ->count();
            if ($withdrawalCount >= $productInfo['buy_count']) {
                throw new RequestException('您已享受过首次福利');
            }
        }

        if ($productInfo['validity_type'] == 'integral') {
            if ($userInfo['integral'] < $productInfo['use_integral']) {
                throw new RequestException('您的正能量不够，需要去举报攒能量');
            }
        } else {
            if ($userInfo['balance'] < $productInfo['price']) {
                throw new RequestException('余额不足');
            }
        }

        $amount = $productInfo['price'];

        try {
            \DB::beginTransaction();
            $withdrawal = UserWithdrawal::query()->create([
                'user_id' => $userId,
                'app_id' => $appId,
                'account_type' => $accountType,
                'amount' => $amount,
                'use_integral' => $productInfo['use_integral'],
                'use_balance' => $productInfo['validity_type'] == 'balance' ? $amount : 0,
                'fund_source' => $productInfo['validity_type'],
                'account' => $account,
                'account_name' => $accountName,
                'apply_time' => time(),
                'audit_status' => UserWithdrawal::AUDIT_STATUS_UNKNOWN,
                'product_id' => $productId,
            ]);

            // 用户积分、余额扣减
            $userInfo['integral'] -= $withdrawal['use_integral'];
            $userInfo['balance'] -= $withdrawal['use_balance'];
            $userInfo->save();

            if ($productInfo['validity_type'] == 'integral') {
                // 增加用户积分使用记录
                $this->userBillService()->income('user_withdrawal_by_energy', $this->getAppId(), $userId, (int) $withdrawal['use_integral'], $userInfo['integral'], $withdrawal['id']);
            } else {
                // 增加用户余额使用记录
                $this->userBillService()->income('user_withdrawal_by_balance', $this->getAppId(), $userId, (int) $withdrawal['use_balance'], $userInfo['balance'], $withdrawal['id']);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw new ApiException($e->getMessage());
        } catch (\Throwable $e) {
            throw new ApiException($e->getMessage());
        }
    }

    public function getAllByPage(array $where, $with = [], $field = '*', $order = 'id desc', $search = true): array
    {
        [$page, $limit] = $this->getPageValue();
        $count = $this->dao->count($where);
        $list = [];
        if ($count > 0) {
            $list = $this->dao->selectList($where, $field, $page, $limit, $order, $search, ['user']);

            foreach ($list as &$item) {
                $item['approval_time'] = $item['status'] != UserWithdrawal::AUDIT_STATUS_UNKNOWN ? date('Y-m-d H:i', $item['update_time']) : '';
                $item['extract_type_name'] = UserWithdrawal::$extractTypeMap[$item['extract_type']] ?? '';
            }
        }

        return compact('list', 'count');
    }

    /**
     * 拒绝用户提现申请
     *
     * @param int $id
     *
     * @return boolean
     */
    public function refuse(int $id, $reason, $adminInfo): bool
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException('申请记录查询失败');
        }

        if ($info['status'] != UserWithdraw::STATUS_AUDIT) {
            throw new AdminException('操作错误，非申请状态');
        }

        $this->dao->update($id, [
            'operator_name' => $adminInfo['real_name'],
            'status' => UserWithdraw::STATUS_FAIL,
            'fail_reason' => $reason,
            'update_time' => time(),
        ]);

        return true;
    }

    /**
     * 通过提现申请
     *
     * @param $id
     *
     * @return bool
     * @throws \think\exception\DbException
     */
    public function adopt(int $id)
    {
        $userExtract = $this->dao->get($id);
        if (!$userExtract) {
            throw new AdminException(100026);
        }
        if ($userExtract->status == 1) {
            throw new AdminException(400659);
        }
        if ($userExtract->status == -1) {
            throw new AdminException(400660);
        }

        $extractNumber = $userExtract['extract_price'];
        /** @var WechatUserServices $wechatServices */
        $wechatServices = app(WechatUserServices::class);
        /** @var UserServices $userServices */
        $userServices = app(UserServices::class);
        $userType = $userServices->value(['uid' => $userExtract['uid']], 'user_type');
        $nickname = $userServices->value(['uid' => $userExtract['uid']], 'nickname');
        $phone = $userServices->value(['uid' => $userExtract['uid']], 'phone');
        event('notice.notice', [['uid' => $userExtract['uid'], 'userType' => strtolower($userType), 'extractNumber' => $extractNumber, 'nickname' => $nickname], 'user_extract']);

        if (!$this->dao->update($id, ['status' => 1])) {
            throw new AdminException(100007);
        }
        switch ($userExtract['extract_type']) {
            case 'bank':
                $order_id = $userExtract['bank_code'];
                break;
            case 'weixin':
                $order_id = $userExtract['wechat'];
                break;
            case 'alipay':
                $order_id = $userExtract['alipay_code'];
                break;
            default:
                $order_id = '';
                break;
        }

        $insertData = ['order_id' => $order_id, 'nickname' => $nickname, 'phone' => $phone];

        $openid = $wechatServices->getWechatOpenid($userExtract['uid'], 'wechat');

        //自动提现到零钱
        if ($userExtract['extract_type'] == 'weixin' && sys_config('brokerage_type', 0) && $openid) {
            /** @var StoreOrderCreateServices $services */
            $services = app(StoreOrderCreateServices::class);
            $insertData['order_id'] = $services->getNewOrderId();

            //v3商家转账到零钱
            if (sys_config('pay_wechat_type')) {
                $pay = new Pay('v3_wechat_pay');
                $res = $pay->merchantPay($openid, $insertData['order_id'], $userExtract['extract_price'], [
                    'batch_name' => '提现佣金到零钱',
                    'batch_remark' => '您于' . date('Y-m-d H:i:s') . '提现.' . $userExtract['extract_price'] . '元',
                ]);
            } else {
                // 微信提现
                $res = WechatServices::merchantPay($openid, $insertData['order_id'], $userExtract['extract_price'], '提现佣金到零钱');
            }

            if (!$res) {
                throw new AdminException(400658);
            }

            // 更新 提现申请记录 wechat_order_id
            $this->dao->update($id, ['wechat_order_id' => $insertData['order_id']]);

            /** @var UserServices $userServices */
            $userServices = app(UserServices::class);
            $user = $userServices->getUserInfo($userExtract['uid']);

            $insertData['nickname'] = $user['nickname'];
            $insertData['phone'] = $user['phone'];
        }

        /** @var CapitalFlowServices $capitalFlowServices */
        $capitalFlowServices = app(CapitalFlowServices::class);
        $capitalFlowServices->setFlow([
            'order_id' => $insertData['order_id'],
            'uid' => $userExtract['uid'],
            'price' => bcmul('-1', $extractNumber, 2),
            'pay_type' => $userExtract['extract_type'],
            'nickname' => $insertData['nickname'],
            'phone' => $insertData['phone'],
        ], 'extract');

        return true;
    }

    /**
     * 代理结算表单
     *
     * @param $id
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function settlementForm($id): array
    {
        $f[] = $this->builder->uploadImage('pay_voucher', '转账截图', url('/admin/admin/file/upload/3', [], false, true), '')
            ->required('请上传转账截图')
            ->headers([
                'Authori-zation' => app()->request->header('Authori-zation'),
            ]);
        $f[] = $this->builder->textarea('remark', '备注')->col(10)->rows(4);

        return create_form('佣金结算', $f, url('/admin/finance/withdraw/settlement/' . $id), 'PUT');
    }

    /**
     * 代理结算
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function settlement(int $id, array $data): bool
    {
        return \DB::transaction(function () use ($id, $data) {
            $info = $this->dao->get($id, [], ['user']);
            if (empty($info) || empty($info['user'])) {
                throw new AdminException(100026);
            }

            // 用户余额扣减
            $info->user->balance -= $info['extract_amount'];
            $info->user->save();

            // 打款凭证保存，提现状态更改
            $data['status'] = UserWithdraw::STATUS_SUCCESS;
            $this->dao->update($id, $data);

            // 更改佣金结算状态
            OrderSpreadBrokerage::update(['is_settlement' => true], ['settlement_id' => $id]);

            return true;
        });
    }
}
