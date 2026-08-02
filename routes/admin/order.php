<?php

/**
 * 订单路由
 */
Route::name('order')->prefix('order')->namespace('Order')->group(function () {
    // 订单列表
    Route::get('member', 'MemberOrderController@index')->name('MemberOrderList');
    // 会员订单退款
    Route::post('member/refund/{id}', 'MemberOrderController@refund')->where(['id' => '[0-9]+']);
    // 会员订单备注
    Route::put('member/remark/{id}', 'MemberOrderController@remark')->where(['id' => '[0-9]+']);
    Route::put('remark/{id}', 'MemberOrderController@remark')->where(['id' => '[0-9]+']);
    // 订阅列表
    Route::get('subscription', 'SubscriptionOrderController@index')->name('SubscriptionOrderList');
});
