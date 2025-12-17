<?php

/**
 * 应用管理
 */
Route::name('app')->prefix('app')->namespace('App')->group(function () {
    // 应用管理
    Route::resource('apps', 'AppsController');
    Route::put('apps/{id}/set_status/{status}', 'AppsController@setStatus');

    // 协议配置
    Route::resource('agreement', 'AgreementsController');
    Route::put('agreement/{id}/set_status/{status}', 'AgreementsController@setStatus');
    Route::post('agreement/{id}/copy', 'AgreementsController@copy');

    // 广告配置
    Route::resource('ad', 'AdvertisementController');
    Route::put('ad/{id}/set_status/{status}', 'AdvertisementController@setStatus');
    Route::post('ad/{id}/copy', 'AdvertisementController@copy');

    // 产品管理
    Route::resource('product', 'ProductController');
    Route::put('product/{id}/set_sort/{sort}', 'ProductController@setSort');
    Route::post('product/{id}/copy', 'ProductController@copy');

    //获取APP版本列表
    Route::get('version', 'VersionController@index');
    //添加版本信息
    Route::get('version/form/{id}', 'VersionController@form');
    //添加版本信息
    Route::post('version', 'VersionController@save');
    // 删除版本信息
    Route::delete('version/del/{id}', 'VersionController@destory');
    // 复制版本信息
    Route::post('version/{id}/copy', 'VersionController@copy');

    // 应用支付配置
    Route::resource('payment', 'PaymentController');
    Route::put('payment/{id}/set_status/{status}', 'PaymentController@setStatus');
    Route::post('payment/{id}/copy', 'PaymentController@copy');

    // 应用配置
    Route::resource('app_config', 'AppConfigController');
    Route::put('app_config/set_field_value/{id}/{value}/{field}', 'AppConfigController@setFieldValue');
    Route::post('app_config/{id}/copy', 'AppConfigController@copy');
});
