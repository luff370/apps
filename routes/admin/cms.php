<?php

/**
 * 内容管理
 */
Route::name('cms')->prefix('cms')->namespace('Cms')->group(function () {
    // 内容分类
    Route::resource('category', 'CategoryController');
    Route::get('category_tree_list', 'CategoryController@getTreeList');
    // 内容管理
    Route::resource('content', 'ContentController');
    // 内容生成
    Route::post('generate', 'ContentController@generate');
    // 排序
    Route::put('content/{id}/{field}/{value}', 'ContentController@setFieldValue');

    // 生成课程
    Route::post('course/generate', 'CourseController@generate');
    // 课程值修改
    Route::put('course/{id}/{field}/{value}', 'CourseController@setFieldValue');
    // 课节删除
    Route::delete('course/{id}', 'CourseController@destroy');

    // 举报数据管理
    Route::resource('traffic_violation_content', 'TrafficViolationContentController');
    Route::put('traffic_violation_content/set_field_value/{id}/{value}/{field}', 'TrafficViolationContentController@setFieldValue');

});
