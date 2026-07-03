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

    // 广告访问统计
    Route::get('ad_access_stat', 'AdAccessLogController@stat');
    // 广告请求明细
    Route::get('ad_access_log', 'AdAccessLogController@index');

    // 应用充值统计
    Route::get('recharge_statistics/summary', 'RechargeStatisticsController@summary');
    Route::get('recharge_statistics/trend', 'RechargeStatisticsController@trend');

    // 版本规划：保存版本计划与渠道任务，供版本规划页替换本地假数据
    Route::get('apps/{appId}/version_plans', 'VersionPlanController@index');
    Route::post('apps/{appId}/version_plans', 'VersionPlanController@save');
    Route::post('apps/{appId}/version_plans/{id}/copy', 'VersionPlanController@copy');
    Route::delete('apps/{appId}/version_plans/{id}', 'VersionPlanController@delete');

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
    Route::put('payment/{id}/set_sort/{sort}', 'PaymentController@setSort');
    Route::post('payment/{id}/copy', 'PaymentController@copy');

    // 应用配置
    Route::resource('app_config', 'AppConfigController');
    Route::put('app_config/set_field_value/{id}/{value}/{field}', 'AppConfigController@setFieldValue');
    Route::post('app_config/{id}/copy', 'AppConfigController@copy');

    // 商户管理
    Route::resource('merchant', 'MerchantController');
    Route::put('merchant/set_field_value/{id}/{value}/{field}', 'MerchantController::class@setFieldValue');

    // API 混淆配置（应用侧只管理别名、映射、导出）
    Route::get('obfuscation', 'ApiObfuscationController@index');
    Route::get('obfuscation/profile', 'ApiObfuscationController@profile');
    Route::post('obfuscation/profile', 'ApiObfuscationController@saveProfile');
    Route::post('obfuscation/profile/generate_defaults', 'ApiObfuscationController@generateDefaults');
    Route::get('obfuscation/aliases', 'ApiObfuscationController@aliases');
    Route::post('obfuscation/aliases', 'ApiObfuscationController@saveAlias');
    Route::delete('obfuscation/aliases/{id}', 'ApiObfuscationController@deleteAlias');
    Route::post('obfuscation/aliases/generate', 'ApiObfuscationController@generateAliases');
    Route::get('obfuscation/aliases/{id}/preview', 'ApiObfuscationController@previewAlias');
    Route::get('obfuscation/export', 'ApiObfuscationController@exportAliases');
    Route::get('obfuscation/export_profile', 'ApiObfuscationController@exportProfile');

});
