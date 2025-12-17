<?php

/**
 * 订单路由
 */
Route::name('order')->prefix('order')->namespace('Order')->group(function () {
    // 订单列表
    Route::get('member', 'MemberOrderController@index')->name('MemberOrderList');
    // 订阅列表
    Route::get('subscription', 'SubscriptionOrderController@index')->name('SubscriptionOrderList');
});

