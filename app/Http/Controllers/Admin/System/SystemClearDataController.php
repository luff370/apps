<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\Controller;
use App\Services\System\SystemClearServices;
use App\Services\Product\StoreProductServices;
use App\Services\System\Attachment\SystemAttachmentServices;

/**
 * 清除默认数据理控制器
 * Class SystemClearData
 *
 * @package app\admin\controller\system
 */
class SystemClearDataController extends Controller
{
    /**
     * 构造方法
     * SystemClearData constructor.
     *
     * @param SystemClearServices $services
     */
    public function __construct(SystemClearServices $services)
    {
        $this->service = $services;
    }

    /**
     * 统一方法
     *
     * @param $type
     */
    public function index($type)
    {
        switch ($type) {
            case 'temp':
                return $this->userTemp();
                break;
            case 'recycle':
                return $this->recycleProduct();
                break;
            case 'store':
                return $this->storeData();
                break;
            case 'category':
                return $this->categoryData();
                break;
            case 'order':
                return $this->orderData();
                break;
            case 'kefu':
                return $this->kefuData();
                break;
            case 'wechat':
                return $this->wechatData();
                break;
            case 'attachment':
                return $this->attachmentData();
                break;
            case 'article':
                return $this->articledata();
                break;
            case 'system':
                return $this->systemdata();
                break;
            case 'user':
                return $this->userRelevantData();
                break;
            case 'wechatuser':
                return $this->wechatuserData();
                break;
            default:
                return $this->fail(100100);
        }
    }

    /**
     * 清除用户生成的临时附件
     */
    public function userTemp()
    {
        /** @var SystemAttachmentServices $services */
        $services = app(SystemAttachmentServices::class);
        $imageUrl = $services->getColumn(['module_type' => 2], 'att_dir');
        foreach ($imageUrl as $item) {
            @unlink(app()->getRootPath() . 'public' . $item);
        }
        $services->delete(2, 'module_type');
        $this->service->clearData(['qrcode'], true);

        return $this->success(100046);
    }

    /**
     * 清除回收站商品
     */
    public function recycleProduct()
    {
        /** @var StoreProductServices $services */
        $services = app(StoreProductServices::class);
        $services->delete(1, 'is_del');

        return $this->success(100046);
    }

    /**
     * 清除用户数据
     */
    public function userRelevantData()
    {
        $this->service->clearData([
            'user_recharge',
            'user_address',
            'user_bill',
            'user_enter',
            'user_extract',
            'user_notice',
            'user_notice_see',
            'wechat_message',
            'store_visit',
            'store_coupon_user',
            'store_coupon_issue_user',
            'store_bargain_user',
            'store_bargain_user_help',
            'store_product_reply',
            'store_product_cate',
            'user_sign',
            'user_level',
            'user_group',
            'user_visit',
            'user_label',
            'user_label_relation',
            'user_label_relation',
            'store_product_relation',
            'sms_record',
            'system_file',
            'system_store',
            'system_store_staff',
            'member_card',
            'member_card_batch',
            'member_ship',
            'qrcode',
            'user_brokerage_frozen',
            'user_invoice',

        ], true);
        $this->service->delDirAndFile('./public/uploads/store/comment');

        return $this->success(100046);
    }

    /**
     * 清除商城数据
     */
    public function storeData()
    {
        $this->service->clearData([
            'store_coupon_issue',
            'store_bargain',
            'store_combination',
            'store_product_attr',
            'store_product_attr_result',
            'store_product_cate',
            'store_product_attr_value',
            'store_product_description',
            'store_product_rule',
            'store_seckill',
            'store_product',
            'store_visit',
            'store_product_log',
            'category',
            'delivery_service',
            'live_anchor',
            'live_goods',
            'live_room',
            'live_room_goods',
            'store_product_coupon',
        ], true);

        return $this->success(100046);
    }

    /**
     * 清除商品分类
     */
    public function categoryData()
    {
        $this->service->clearData(['store_category'], true);

        return $this->success(100046);
    }

    /**
     * 清除订单数据
     */
    public function orderData()
    {
        $this->service->clearData([
            'store_order',
            'store_order_cart_info',
            'store_order_status',
            'store_pink',
            'store_cart',
            'store_order_status',
            'other_order',
            'other_order_status',
            'store_order_invoice',
        ], true);

        return $this->success(100046);
    }

    /**
     * 清除客服数据
     */
    public function kefuData()
    {
        $this->service->clearData([
            'store_service',
            'store_service_log',
            'store_service_record',
            'store_service_feedback',
            'store_service_speechcraft',
        ], true);
        $this->service->delDirAndFile('./public/uploads/store/service');

        return $this->success(100046);
    }

    /**
     * 清除微信管理数据
     */
    public function wechatData()
    {
        $this->service->clearData([
            'wechat_media',
            'wechat_reply',
            'cache',
            'wechat_key',
            'wechat_news_category',
        ], true);
        $this->service->delDirAndFile('./public/uploads/wechat');

        return $this->success(100046);
    }

    /**
     * 清除所有附件
     */
    public function attachmentData()
    {
        $this->service->clearData([
            'system_attachment',
            'system_attachment_category',
        ], true);
        $this->service->delDirAndFile('./public/uploads/');

        return $this->success(100046);
    }

    /**
     * 清除微信用户
     */
    public function wechatuserData()
    {
        $this->service->clearData([
            'user',
            'wechat_user',
        ], true);

        return $this->success(100046);
    }

    //清除内容分类
    public function articledata()
    {
        $this->service->clearData([
            'article_category',
            'article',
            'article_content',
        ], true);

        return $this->success(100046);
    }

    //清除系统记录
    public function systemdata()
    {
        $this->service->clearData([
            'system_notice_admin',
            'system_log',
        ], true);

        return $this->success(100046);
    }

    /**
     * 替换域名方法
     */
    public function replaceSiteUrl()
    {
        [$url] = $this->getMore([
            ['url', ''],
        ], true);
        if (!$url) {
            return $this->fail(400304);
        }
        if (!verify_domain($url)) {
            return $this->fail(400305);
        }
        $this->service->replaceSiteUrl($url);

        return $this->success(400306);
    }
}
