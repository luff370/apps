<?php

/**
 * 文件下载、导出相关路由
 */
Route::name('common.')->group(function () {
    // 首页统计数据
    Route::get('home/header', 'CommonController@homeStatics');
    // 首页订单图表
    Route::get('home/order', 'CommonController@orderChart');
    // 首页用户图表
    Route::get('home/user', 'CommonController@userChart');
    // 消息提醒
    Route::get('notice', 'CommonController@notice');
    // 获取左侧菜单
    Route::get('menus', 'CommonController@menus');
    // 获取搜索菜单列表
    Route::get('menusList', 'CommonController@menusList');
    // 获取logo
    Route::get('logo', 'CommonController@siteInfo');
    // select表单数据列表
    Route::get('form/select_list', 'CommonController@formSelectList');
});

