<?php

/**
 * 商户管理 相关路由
 */
Route::name('freight')->prefix('freight')->namespace('Freight')->group(function () {
    //物流公司资源路由
    Route::resource('express', 'ExpressController');                        //修改状态
    Route::put('express/set_status/{id}/{status}', 'ExpressController@set_status');
    //同步物流快递公司
    Route::get('express/sync_express', 'ExpressController@syncExpress');    //物流配置编辑表单
    Route::get('config/edit_basics', 'SystemConfig@edit_basics');
    //物流配置保存数据
    Route::post('config/save_basics', 'SystemConfig@save_basics');
});
