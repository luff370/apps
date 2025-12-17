<?php

Route::name('product')->prefix('product')->namespace('Product')->group(function () {
    //商品列表头
    Route::get('type_header', 'ProductController@type_header');
    //商品列表
    Route::get('', 'ProductController@index');
    //商品详情
    Route::get('{id}', 'ProductController@info')->where('id', '[0-9]+');
    //加入回收站
    Route::delete('{id}', 'ProductController@delete')->where('id', '[0-9]+');
    //保存新建或保存
    Route::post('{id}', 'ProductController@save')->where('id', '[0-9]+');
    //修改商品状态
    Route::put('set_show/{id}/{is_show}', 'ProductController@set_show')->where(['id' => '[0-9]+', 'is_show' => '[0-9]+']);
    //修改商品排序
    Route::put('set_sort/{id}/{sort}', 'ProductController@set_sort')->where(['id' => '[0-9]+', 'sort' => '[0-9]+']);
    //商品快速编辑
    Route::put('set_product/{id}', 'ProductController@set_product')->where('id', '[0-9]+');
    //设置批量商品上架
    Route::put('product_show', 'ProductController@product_show');
    //设置批量商品下架
    Route::put('product_unshow', 'ProductController@product_unshow');
    //获取规则属性模板
    Route::get('get_rule', 'ProductController@get_rule');
    //获取运费模板
    Route::get('get_template', 'ProductController@get_template');
    // 商品规格删除-检查产品活动状态
    Route::get('check_activity/{id}', 'ProductController@checkActivity');

    //生成属性
    Route::post('generate_attr/{id}/{type}', 'ProductController@is_format_attr');
    //获取商品规格
    Route::get('attrs/{id}/{type}', 'ProductController@get_attrs');
    //1分钟保存一次数据
    Route::post('cache', 'ProductController@saveCacheData');
    //获取退出未保存的数据
    Route::get('cache', 'ProductController@getCacheData');
    // 商品批量操作
    Route::post('batch/setting', 'ProductController@batchSetting');
    // 商品批量操作
    Route::post('clear_list_cache', 'ProductController@clearListCache');
    // 设置退回收货地址
    Route::get('{id}/return_back_address', 'ProductController@returnBackAddressForm');
    Route::put('{id}/return_back_address', 'ProductController@setReturnBackAddress');

    // 商品分类管理
    Route::name('category')->prefix('category')->group(function () {
        Route::get('', 'CategoryController@index');
        //商品树形列表
        Route::get('/tree/{type}', 'CategoryController@tree_list');
        //商品分类新增表单
        Route::get('/create', 'CategoryController@create');
        //商品分类新增
        Route::post('', 'CategoryController@save');
        //商品分类编辑表单
        Route::get('/{id}', 'CategoryController@edit')->where('id', '[0-9]+');
        //商品分类编辑
        Route::put('/{id}', 'CategoryController@update')->where('id', '[0-9]+');
        //删除商品分类
        Route::delete('/{id}', 'CategoryController@delete')->where('id', '[0-9]+');
        //商品分类修改状态
        Route::put('/set_show/{id}/{is_show}', 'CategoryController@set_show');
        //商品分类快捷编辑
        Route::put('/set_category/{id}', 'CategoryController@set_category')->where('id', '[0-9]+');
    });

    // 商品品牌管理
    Route::name('brand')->group(function () {
        // 商品品牌资源路由
        Route::resource('brand', 'ProductBrandController');
        // 商品品牌修改状态
        Route::put('brand/set_status/{id}/{is_show}', 'ProductBrandController@set_show');
    });

    // 商品属性管理
    Route::name('rule')->prefix('rule')->group(function () {
        //规则列表
        Route::get('', 'ProductRuleController@index');
        //规则 保存新建或编辑
        Route::post('/{id}', 'ProductRuleController@save');
        //规则详情
        Route::get('/{id}', 'ProductRuleController@read');
        //删除属性规则
        Route::delete('/delete', 'ProductRuleController@delete');
    });

    // 商品评论管理
    Route::name('reply')->prefix('reply')->group(function () {
        //评论列表
        Route::get('', 'ProductReplyController@index');
        //回复评论
        Route::put('/set_reply/{id}', 'ProductReplyController@set_reply');
        //删除评论
        Route::delete('/{id}', 'ProductReplyController@delete');
        //调起虚拟评论表单
        Route::get('/fictitious_reply/:product_id', 'ProductReplyController@fictitious_reply');
        //保存虚拟评论
        Route::post('/save_fictitious_reply', 'ProductReplyController@save_fictitious_reply');
    });
});
