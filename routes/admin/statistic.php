<?php


/**
 * 分销管理 相关路由
 */
Route::name('statistic')->prefix('statistic')->namespace('Statistic')->group( function () {
    /** 用户统计 */
    //用户基础
    Route::get('user/get_basic', 'UserStatisticController@getBasic');

    //用户增长趋势
    Route::get('user/get_trend', 'UserStatisticController@getTrend');

    //微信用户
    Route::get('user/get_wechat', 'UserStatisticController@getWechat');

    //微信用户成长趋势
    Route::get('user/get_wechat_trend', 'UserStatisticController@getWechatTrend');

    //用户地域排行
    Route::get('user/get_region', 'UserStatisticController@getRegion');

    //用户性别
    Route::get('user/get_sex', 'UserStatisticController@getSex');

    //商品数据导出
    Route::get('user/get_excel', 'UserStatisticController@getExcel');


    /** 商品统计 */
    //商品基础
    Route::get('product/get_basic', 'ProductStatisticController@getBasic');

    //商品趋势
    Route::get('product/get_trend', 'ProductStatisticController@getTrend');
    //商品排行
    Route::get('product/get_product_ranking', 'ProductStatisticController@getProductRanking');
    /** 交易统计 */
    //今日营业额统计
    Route::get('trade/top_trade', 'TradeStatisticController@topTrade');

    Route::get('trade/bottom_trade', 'TradeStatisticController@bottomTrade');

    /** 订单统计 */
    //订单基础
    Route::get('order/get_basic', 'OrderStatisticController@getBasic');

    //订单趋势
    Route::get('order/get_trend', 'OrderStatisticController@getTrend');
    //订单来源
    Route::get('order/get_channel', 'OrderStatisticController@getChannel');
    //订单类型
    Route::get('order/get_type', 'OrderStatisticController@getType');
    /** 资金流水 */
    Route::get('flow/get_list', 'FlowStatisticController@getFlowList');
    Route::post('flow/set_mark/{id}', 'FlowStatisticController@setMark');
    Route::get('flow/get_record', 'FlowStatisticController@getFlowRecord');
    /** 余额统计 */
    //余额基础统计
    Route::get('balance/get_basic', 'BalanceStatisticController@getBasic');

    //余额趋势
    Route::get('balance/get_trend', 'BalanceStatisticController@getTrend');
    //余额来源
    Route::get('balance/get_channel', 'BalanceStatisticController@getChannel');
    //余额消耗
    Route::get('balance/get_type', 'BalanceStatisticController@getType');
});

Route::get('statistic/product/get_excel', 'ProductStatisticController@getExcel');
Route::get('statistic/product/collect', 'ProductStatisticController@collect');
