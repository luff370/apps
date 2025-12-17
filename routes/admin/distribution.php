<?php

/**
 * 分销 相关路由
 */
Route::name('distribution.')->prefix('distribution')->namespace('Distribution')->group( function () {
    //代理商资源路由
    Route::resource('agent', 'AgentController');
    //修改状态
    Route::put('agent/set_status/{id}/:status', 'AgentController@set_status');
    // 代理手动结算
    Route::post('agent/{id}/settlement', 'AgentController@settlement');

    //推广员资源路由
    Route::resource('spread', 'SpreadController');
    //修改状态
    Route::put('spread/set_status/{id}/:status', 'SpreadController@set_status');
    //分销类型资源路由
    Route::resource('category', 'CategoryController');
    //修改状态
    Route::put('category/set_show/{id}/:is_show', 'CategoryController@set_show');

    //短连接
    Route::resource('short_url', 'ShortUrlController');
    Route::post('short_url/cache', 'ShortUrlController@cache');
    Route::get('short_url/{id}/set_cost', 'ShortUrlController@costForm');
    Route::put('short_url/{id}/set_cost', 'ShortUrlController@setCost');
});
