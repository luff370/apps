<?php

use Illuminate\Support\Facades\Route;

/**
 * 系统设置维护 系统权限管理、系统菜单管理 系统配置 相关路由
 */

Route::name('setting')->prefix('setting')->namespace('Setting')->group(function () {
    //管理员资源路由
    Route::resource('admin', 'SystemAdminController');                                                                                                            //修改状态
    Route::put('set_status/{id}/{status}', 'SystemAdminController@set_status')->name('SystemAdminSetStatus')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);    //获取当前管理员信息
    Route::get('info', 'SystemAdminController@info')->name('SystemAdminInfo');
    //修改当前管理员信息
    Route::put('update_admin', 'SystemAdminController@update_admin')->name('SystemAdminUpdateAdmin');
    //设置文件管理密码
    Route::put('set_file_password', 'SystemAdminController@set_file_password')->name('SystemAdminSetFilePassword');
    //权限菜单资源路由
    Route::resource('menus', 'SystemMenuController');                                                                                                            //未添加的权限规则列表
    Route::get('ruleList', 'SystemMenuController@ruleList');
    //修改显示
    Route::put('menus/show/{id}', 'SystemMenuController@setShow')->name('SystemMenusShow');
    //身份列表
    Route::get('role', 'SystemRoleController@index');                                                                                                             //身份权限列表
    Route::get('role/create', 'SystemRoleController@create');
    //编辑详情
    Route::get('role/{id}/edit', 'SystemRoleController@edit');                                                                                                    //保存新建或编辑
    Route::post('role/{id}', 'SystemRoleController@save');
    //修改身份状态
    Route::put('role/set_status/{id}/{status}', 'SystemRoleController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //删除身份
    Route::delete('role/{id}', 'SystemRoleController@delete');

    //配置分类资源路由
    Route::resource('config_class', 'SystemConfigTabController');
    //修改配置分类状态
    Route::put('config_class/set_status/{id}/{status}', 'SystemConfigTabController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    // 同步应用配置
    Route::post('config/sync_config_form_other_app/{fromAppId}/{toAppID}', 'SystemConfigTabController@syncFromOtherAppConfig')->where(['fromAppId' => '[0-9]+', 'toAppID' => '[0-9]+']);

    // 基本配置编辑表单
    Route::put('config/set_status/{id}/{status}', 'SystemConfigController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    Route::get('config/header_basics', 'SystemConfigController@header_basics');
    //基本配置编辑表单
    Route::get('config/edit_basics', 'SystemConfigController@edit_basics');
    //基本配置保存数据
    Route::post('config/save_basics', 'SystemConfigController@save_basics');
    //基本配置上传文件
    Route::post('config/upload', 'SystemConfigController@file_upload');
    //获取单个配置值
    Route::get('config/get_system/{name}', 'SystemConfigController@get_system');
    //获取某个分类下的所有配置信息
    Route::get('config_list/{tabId}', 'SystemConfigController@get_config_list');

    //配置资源路由
    Route::resource('config', 'SystemConfigController');                                                                                                          //修改配置状态

    //组合数据资源路由
    Route::resource('group', 'SystemGroupController');                                                                                                            //组合数据全部
    Route::get('group_all', 'SystemGroupController@getGroup');
    Route::get('group_data/header', 'SystemGroupDataController@header');
    //组合数据子数据资源路由
    Route::resource('group_data', 'SystemGroupDataController');                                                                                                   //修改数据状态
    // 修改数据状态
    Route::put('group_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //数据配置保存
    Route::post('group_data/save_all', 'SystemGroupDataController@saveAll');                                                                                      //获取城市数据列表
    Route::get('city/list/{parent_id}', 'SystemCityController@index');
    //添加城市数据表单
    Route::get('city/add/{parent_id}', 'SystemCityController@add');
    //修改城市数据表单
    Route::get('city/{id}/edit', 'SystemCityController@edit')->where('id', '[0-9]+');
    //新增/修改城市数据
    Route::post('city/save', 'SystemCityController@save');
    //修改城市数据表单
    Route::delete('city/del/{city_id}', 'SystemCityController@delete');                                                                                            //清除城市数据缓存
    Route::get('city/clean_cache', 'SystemCityController@clean_cache');
    //运费模板列表
    Route::get('shipping_templates/list', 'ShippingTemplateController@temp_list');                                                                                //修改运费模板数据
    Route::get('shipping_templates/{id}/edit', 'ShippingTemplateController@edit');
    //保存新增修改
    Route::post('shipping_templates/save/{id}', 'ShippingTemplateController@save');
    //删除运费模板
    Route::delete('shipping_templates/del/{id}', 'ShippingTemplateController@delete');                                                                            //城市数据接口
    Route::get('shipping_templates/city_list', 'ShippingTemplateController@city_list');                                                                           //获取客服广告
    Route::get('get_kf_adv', 'SystemGroupDataController@getKfAdv');                                                                                               //设置客服广告
    Route::post('set_kf_adv', 'SystemGroupDataController@setKfAdv');
    //签到天数配置资源
    Route::resource('sign_data', 'SystemGroupDataController');                                                                                                    //签到数据字段
    Route::get('sign_data/header', 'SystemGroupDataController@header');                                                                                           //修改签到数据状态
    Route::put('sign_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //订单详情动态图配置资源
    Route::resource('order_data', 'SystemGroupDataController');
    //订单数据字段
    Route::get('order_data/header', 'SystemGroupDataController@header');                                                                                          //订单数据状态
    Route::put('order_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);                   //个人中心菜单配置资源
    Route::resource('usermenu_data', 'SystemGroupDataController');                                                                                                //个人中心菜单数据字段
    Route::get('usermenu_data/header', 'SystemGroupDataController@header');
    //个人中心菜单数据状态
    Route::put('usermenu_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //分享海报配置资源
    Route::resource('poster_data', 'SystemGroupDataController');                                                                                                  //分享海报数据字段
    Route::get('poster_data/header', 'SystemGroupDataController@header');
    //分享海报数据状态
    Route::put('poster_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //秒杀配置资源
    Route::resource('seckill_data', 'SystemGroupDataController');                                                                                                 //秒杀数据字段
    Route::get('seckill_data/header', 'SystemGroupDataController@header');                                                                                        //秒杀数据状态
    Route::put('seckill_data/set_status/{id}/{status}', 'SystemGroupDataController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);                 //获取隐私协议
    Route::get('get_user_agreement', 'SystemGroupDataController@getUserAgreement');                                                                               //设置隐私协议
    Route::post('set_user_agreement', 'SystemGroupDataController@setUserAgreement');                                                                              //系统通知
    //系统通知列表
    Route::get('notification/index', 'SystemNotificationController@index');                                                                                       //获取单条数据
    Route::get('notification/info', 'SystemNotificationController@info');
    //保存通知设置
    Route::post('notification/save', 'SystemNotificationController@save');                                                                                        //修改消息状态
    Route::put('notification/set_status/{type}/{status}/{id}', 'SystemNotificationController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);       //协议设置
    Route::get('get_agreement/:type', 'SystemAgreementController@getAgreement');
    Route::post('save_agreement', 'SystemAgreementController@saveAgreement');                       //获取版权信息
    Route::get('get_version', 'SystemConfigController@getVersion');                                 //对外接口账号信息
    Route::get('system_out_account/index', 'SystemOutAccountController@index');
    //对外接口账号添加
    Route::post('system_out_account/save', 'SystemOutAccountController@save');
    //对外接口账号修改
    Route::post('system_out_account/update/{id}', 'SystemOutAccountController@update');
    //设置账号是否禁用
    Route::put('system_out_account/set_status/{id}/{status}', 'SystemOutAccountController@set_status')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //设置账号推送接口
    Route::put('system_out_account/set_up/{id}', 'SystemOutAccountController@outSetUpSave');
    //删除账号
    Route::delete('system_out_account/{id}', 'SystemOutAccountController@delete');                  //测试获取token接口
    Route::post('system_out_account/text_out_url', 'SystemOutAccountController@textOutUrl');

    //对外接口列表
    Route::get('system_out_interface/list', 'SystemOutAccountController@outInterfaceList');         //新增修改对外接口
    Route::post('system_out_interface/save/{id}', 'SystemOutAccountController@saveInterface');
    //对外接口信息
    Route::get('system_out_interface/info/{id}', 'SystemOutAccountController@interfaceInfo');       //修改接口名称
    Route::put('system_out_interface/edit_name', 'SystemOutAccountController@editInterfaceName');   //删除接口
    Route::delete('system_out_interface/del/{id}', 'SystemOutAccountController@delInterface');
    /** 多语言 */
    //语言国家列表
    Route::get('lang_country/list', 'LangCountryController@langCountryList');                       //添加语言地区表单
    Route::get('lang_country/form/{id}', 'LangCountryController@langCountryForm');
    //保存语言地区
    Route::post('lang_country/save/{id}', 'LangCountryController@langCountrySave');                 //删除语言地区
    Route::delete('lang_country/del/{id}', 'LangCountryController@langCountryDel');                 //语言类型列表
    Route::get('lang_type/list', 'LangTypeController@langTypeList');                                //新增修改语言类型表单
    Route::get('lang_type/form/{id}', 'LangTypeController@langTypeForm');
    //保存新增修改语言
    Route::post('lang_type/save/{id}', 'LangTypeController@langTypeSave');
    //删除语言
    Route::delete('lang_type/del/{id}', 'LangTypeController@langTypeDel');                          //修改语言类型状态
    Route::put('lang_type/status/{id}/{status}', 'LangTypeController@langTypeStatus')->where(['id' => '[0-9]+', 'status' => '[0-9]+']);
    //获取语言列表
    Route::get('lang_code/list', 'LangCodeController@langCodeList');                                //获取语言信息
    Route::get('lang_code/info', 'LangCodeController@langCodeInfo');                                //保存修改语言
    Route::post('lang_code/save', 'LangCodeController@langCodeSave');                               //删除语言
    Route::delete('lang_code/del/{id}', 'LangCodeController@langCodeDel');
});
