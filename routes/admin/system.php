<?php

/**
 * 维护 相关路由
 */
Route::name('system')->prefix('system')->namespace('System')->group( function () {
    // 支付管理
    Route::resource('payment', 'PaymentController');
    // 状态设置
    Route::put('payment/{id}/set_status/{status}', 'PaymentController@setStatus');

    //云存储列表
    Route::get('config/storage/save_type/:type', 'SystemStorageController@uploadType')->name('SystemStorageUploadType');

    //云存储列表
    Route::get('config/storage', 'SystemStorageController@index')->name('SystemStorageIndex');
    //获取云存储创建表单
    Route::get('config/storage/create/:type', 'SystemStorageController@create')->name('SystemStorageCreate');
    //获取云存储配置表单
    Route::get('config/storage/form/:type', 'SystemStorageController@getConfigForm')->name('getConfigForm');
    //获取云存储配置
    Route::get('config/storage/config', 'SystemStorageController@getConfig')->name('SystemStorageConfig');

    //保存云存储配置
    Route::post('config/storage/config', 'SystemStorageController@saveConfig')->name('SystemStorageSaveConfig');

    //同步云存储列表
    Route::put('config/storage/synch/:type', 'SystemStorageController@synch')->name('SystemStorageSynch');

    //获取修改云存储域名表单
    Route::get('config/storage/domain/{id}', 'SystemStorageController@getUpdateDomainForm')->name('getUpdateDomainForm');
    //修改云存储域名
    Route::post('config/storage/domain/{id}', 'SystemStorageController@updateDomain')->name('updateDomain');

    //保存云存储数据
    Route::post('config/storage/:type', 'SystemStorageController@save')->name('SystemStorageSave');

    //删除云存储
    Route::delete('config/storage/{id}', 'SystemStorageController@delete')->name('SystemStorageDelete');
        //修改云存储状态
    Route::put('config/storage/status/{id}', 'SystemStorageController@status')->name('SystemStorageStatus');

    //系统日志
    Route::get('log', 'SystemLogController@index')->name('SystemLog');
       //系统日志管理员搜索条件
    Route::get('log/search_admin', 'SystemLogController@search_admin');
    //文件校验
    Route::get('file', 'SystemFileController@index')->name('SystemFile');
    //数据所有表
    Route::get('backup', 'SystemDatabackupController@index');

    //数据备份详情
    Route::get('backup/read', 'SystemDatabackupController@read');

    //数据备份 优化表
    Route::put('backup/optimize', 'SystemDatabackupController@optimize');

    //数据备份 修复表
    Route::put('backup/repair', 'SystemDatabackupController@repair');

    //数据备份 备份表
    Route::put('backup/backup', 'SystemDatabackupController@backup');

    //备份记录
    Route::get('backup/file_list', 'SystemDatabackupController@fileList');

    //删除备份记录
    Route::delete('backup/del_file', 'SystemDatabackupController@delFile');
    //导入备份记录表
    Route::post('backup/import', 'SystemDatabackupController@import');
    //下载备份记录表
//        Route::get('backup/download', 'SystemDatabackupController@downloadFile');
    //清除用户数据
    Route::get('clear/:type', 'SystemClearDataController@index');

    //清除缓存
    Route::get('refresh_cache/cache', 'ClearController@refresh_cache');

    //清除日志
    Route::get('refresh_cache/log', 'ClearController@delete_log');

    //域名替换接口
    Route::post('replace_site_url', 'SystemClearDataController@replaceSiteUrl');
    //获取APP版本列表
    Route::get('version_list', 'AppVersionController@list');
    //添加版本信息
    Route::get('version_crate/{id}', 'AppVersionController@crate');
    //添加版本信息
    Route::post('version_save', 'AppVersionController@save');
    //升级状态
    Route::get('upgrade_status', 'UpgradeController@upgradeStatus');
    //升级包列表
    Route::get('upgrade/list', 'UpgradeController@upgradeList');
    //可升级包列表
    Route::get('upgradeable/list', 'UpgradeController@upgradeableList');

    //升级协议
    Route::get('upgrade/agreement', 'UpgradeController@agreement');
    //升级包下载
    Route::post('upgrade_download/:package_key', 'UpgradeController@download');
    //升级进度
    Route::get('upgrade_progress', 'UpgradeController@progress');
    //升级记录
    Route::get('upgrade_log/list', 'UpgradeController@upgradeLogList');
    //导出备份项目
    Route::get('upgrade_export/{id}/:type', 'UpgradeController@export');
    //文件管理登录
    Route::post('file/login', 'SystemFileController@login');


    //打开目录
    Route::get('file/opendir', 'SystemFileController@opendir');
    //读取文件
    Route::get('file/openfile', 'SystemFileController@openfile');
    //保存文件
    Route::post('file/savefile', 'SystemFileController@savefile');
    //创建文件夹
    Route::get('file/createFolder', 'SystemFileController@createFolder');
    //创建文件
    Route::get('file/createFile', 'SystemFileController@createFile');
    //删除文件夹或者文件
    Route::get('file/delFolder', 'SystemFileController@delFolder');
    //重命名文件
    Route::get('file/rename', 'SystemFileController@rename');

    // 短信发送记录
    Route::get('sms/records', 'SmsRecordController@index');
});



