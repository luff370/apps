<?php

/**
 * 财务模块 相关路由
 */
Route::name('finance')->prefix('finance')->namespace('Finance')->group( function () {
    /** 资金流水 */
    // 资金流水-列表
    Route::get('capital_flow', 'CapitalFlowController@index');                         // 资金流水-备注
    Route::put('capital_flow/{id}/remark', 'CapitalFlowController@setMark');
    /** 佣金记录 */
    //佣金记录列表
    Route::get('brokerage_records', 'BrokerageRecordsController@index');               //佣金统计
    Route::get('brokerage_records/stat', 'BrokerageRecordsController@stat');           //佣金提现记录
    Route::get('brokerage_records/withdraw', 'BrokerageRecordsController@withdraw');
    /** 提现申请 */
    //申请列表
    Route::get('withdraw', 'UserWithdrawController@index');                            // 提现详情信息
    Route::get('withdraw/{id}', 'UserWithdrawController@show');
    //编辑表单
    Route::get('withdraw/{id}/edit', 'UserWithdrawController@edit');
    //保存修改
    Route::put('withdraw/{id}', 'UserWithdrawController@update');                       //拒绝申请
    Route::put('withdraw/{id}/refuse', 'UserWithdrawController@refuse');                //通过申请
    Route::put('withdraw/{id}/adopt', 'UserWithdrawController@adopt');                  //手动结算表单
    Route::get('withdraw/{id}/settlement', 'UserWithdrawController@settlementForm');    //手动结算
    Route::put('withdraw/{id}/settlement', 'UserWithdrawController@settlement');        //备注
    Route::put('withdraw/{id}/remark', 'UserWithdrawController@remark');

    // 用户提现申请管理
    Route::resource('user_withdrawal', 'UserWithdrawalController');
    Route::put('user_withdrawal/{id}/adopt', 'UserWithdrawalController@adopt');
    Route::put('user_withdrawal/set_field_value/{id}/{value}/{field}', 'UserWithdrawalController::class@setFieldValue');
});
