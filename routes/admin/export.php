<?php

/**
 * 导出excel相关路由
 */
Route::name('export')->prefix('export')->group( function () {
    //用户资金监控
    Route::get('userFinance', 'ExportExcelController@userFinance');
    //用户佣金
    Route::get('userCommission', 'ExportExcelController@userCommission');
    //用户积分
    Route::get('userPoint', 'ExportExcelController@userPoint');
    //用户充值
    Route::get('userRecharge', 'ExportExcelController@userRecharge');
    //分销用户推广列表
    Route::get('userAgent', 'ExportExcelController@userAgent');
    //微信用户
    Route::get('wechatUser', 'ExportExcelController@wechatUser');
    //商铺砍价活动
    Route::get('storeBargain', 'ExportExcelController@storeBargain');
    //商铺拼团
    Route::get('storeCombination', 'ExportExcelController@storeCombination');
    //商铺秒杀
    Route::get('storeSeckill', 'ExportExcelController@storeSeckill');
    //商铺产品
    Route::get('storeProduct', 'ExportExcelController@storeProduct');    //商铺订单
    Route::get('storeOrder', 'ExportExcelController@storeOrder');    //商铺提货点
    Route::get('storeMerchant', 'ExportExcelController@storeMerchant');
    //导出会员卡
    Route::get('memberCard/{id}', 'ExportExcelController@memberCard');
});
