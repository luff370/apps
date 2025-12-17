<?php

/**
 * 附件相关路由
 */
Route::name('file')->prefix('file')->namespace('File')->group(function () {
    //附件列表
    Route::get('file', 'SystemAttachmentController@index');
    //删除图片和数据记录
    Route::post('file/delete', 'SystemAttachmentController@delete');
    //移动图片分来表单
    Route::get('file/move', 'SystemAttachmentController@move');
    //移动图片分类
    Route::put('file/do_move', 'SystemAttachmentController@moveImageCate');
    //修改图片名称
    Route::put('file/update/{id}', 'SystemAttachmentController@update');
    //上传图片
    Route::post('upload', 'SystemAttachmentController@upload');
    //附件分类管理资源路由
    Route::resource('category', 'SystemAttachmentCategoryController');
    //获取上传类型
    Route::get('upload_type', 'SystemAttachmentController@uploadType');
    //分片上传本地视频
    Route::post('video_upload', 'SystemAttachmentController@videoUpload');
});
