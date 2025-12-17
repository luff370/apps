<?php

namespace App\Support\Traits;

trait ServicesTrait
{

    /**
     * 应用信息service
     */
    public function appService(): \App\Services\App\AppsService
    {
        return app(\App\Services\App\AppsService::class);
    }

    /**
     * 用户service
     */
    public function userService(): \App\Services\User\UserServices
    {
        return app(\App\Services\User\UserServices::class);
    }

    /**
     * 分类service
     */
    public function categoryService(): \App\Services\Cms\CategoryService
    {
        return app(\App\Services\Cms\CategoryService::class);
    }

    /**
     * 用户费用明细记录service
     */
    public function userBillService(): \App\Services\User\UserBillServices
    {
        return app(\App\Services\User\UserBillServices::class);
    }

    /**
     * 用户提现service
     */
    public function userWithdrawalService(): \App\Services\Finance\UserWithdrawalService
    {
        return app(\App\Services\Finance\UserWithdrawalService::class);
    }

    /**
     * 转账订单service
     */
    public function transferOrderService(): \App\Services\Order\TransferOrderService
    {
        return app(\App\Services\Order\TransferOrderService::class);
    }

    /**
     * 用户统计service
     */
    public function userStatisticsService(): \App\Services\Statistics\UserStatisticsService
    {
        return app(\App\Services\Statistics\UserStatisticsService::class);
    }

    /**
     * 文字内容service
     */
    public function articleService(): \App\Services\Cms\ContentService
    {
        return app(\App\Services\Cms\ContentService::class);
    }

    /**
     * 课节内容service
     */
    public function articleCourseService(): \App\Services\Cms\ArticleCourseService
    {
        return app(\App\Services\Cms\ArticleCourseService::class);
    }

    /**
     * 系统配置service
     */
    public function systemConfigServices(): \App\Services\System\Config\SystemConfigServices
    {
        return app(\App\Services\System\Config\SystemConfigServices::class);
    }

    /**
     * 系统配置service
     */
    public function systemConfigTabServices(): \App\Services\System\Config\SystemConfigTabServices
    {
        return app(\App\Services\System\Config\SystemConfigTabServices::class);
    }

    /**
     * 红包发放service
     */
    public function redEnvelopeService(): \App\Services\Activity\RedEnvelopeService
    {
        return app(\App\Services\Activity\RedEnvelopeService::class);
    }

}
