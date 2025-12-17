<?php

namespace App\Http\Controllers\Admin\User\Member;

use App\Http\Controllers\Admin\Controller;
use App\Services\Other\QrcodeServices;
use App\Services\Other\AgreementServices;
use App\Services\User\Member\MemberCardBatchServices;

/**
 * Class MemberCardBatch
 *
 * @package App\Http\Controllers\Admin\User\Member
 */
class MemberCardBatch extends Controller
{
    /**
     * MemberCardBatch constructor.
     *
     * @param MemberCardBatchServices $memberCardBatchServices
     */
    public function __construct(MemberCardBatchServices $memberCardBatchServices)
    {
        $this->service = $memberCardBatchServices;
    }

    /**
     * 会员卡批次资源列表
     */
    public function index()
    {
        $where = $this->getMore([
            ['title', ''],
            //            ['page', 1],
            //            ['limit', 20],
        ]);
        $data = $this->service->getList($where);

        return $this->success($data);
    }

    /** 保存卡片资源
     *
     * @param $id
     */
    public function save($id)
    {
        $data = $this->getMore([
            ['title', ''],
            ['use_day', 1],
            ['total_num', 1],
            ['status', 0],
            ['remark', ''],
        ]);
        $this->service->save((int) $id, $data);

        return $this->success(400313);
    }

    /**
     * 列表操作
     *
     * @param $id
     */
    public function set_value($id)
    {
        $data = $this->getMore([
            ['value', ''],
            ['field', ''],
        ]);
        $this->service->setValue($id, $data);

        return $this->success(100001);
    }

    /**会员二维码，兑换卡
     */
    public function member_scan()
    {
        //生成h5地址
        $weixinPage = "/pages/annex/vip_active/index";
        $weixinFileName = "wechat_member_card.png";
        /** @var QrcodeServices $QrcodeServices */
        $QrcodeServices = app(QrcodeServices::class);
        $wechatQrcode = $QrcodeServices->getWechatQrcodePath($weixinFileName, $weixinPage, false, false);
        //生成小程序地址
        $routineQrcode = $QrcodeServices->getRoutineQrcodePath(4, 6, 4, [], false);

        return $this->success(['wechat_img' => $wechatQrcode, 'routine' => $routineQrcode ?: ""]);
    }

    /** 添加会员协议
     *
     * @param int $id
     * @param AgreementServices $agreementServices
     */
    public function save_member_agreement($id = 0, AgreementServices $agreementServices)
    {
        $data = $this->getMore([
            ['type', 1],
            ['title', ""],
            ['content', ''],
            ['status', ''],
        ]);

        return $this->success($agreementServices->saveAgreement($data, $id));
    }

    /**获取会员协议
     *
     * @param AgreementServices $agreementServices
     */
    public function getAgreement(AgreementServices $agreementServices)
    {
        $list = $agreementServices->getAgreementBytype(1);

        return $this->success($list);
    }
}
