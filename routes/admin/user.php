<?php

use Illuminate\Support\Facades\Route;

/**
 * 用户模块 相关路由
 */
Route::name('user')->prefix('user')->namespace('User')->group( function () {
    //用户管理资源路由
    Route::resource('user', 'UserController');
    Route::post('user/save', 'UserController@save_info');
    //同步微信用户
    Route::get('user/syncUsers', 'UserController@syncWechatUsers');
    //用户信息
    Route::get('user/user_save_info/{uid}', 'UserController@userSaveInfo');
    //用户表单头
    Route::get('user/type_header', 'UserController@type_header');

    //赠送会员等级
    Route::get('give_level/{id}', 'UserController@give_level');
    //执行赠送会员等级
    Route::put('save_give_level/{id}', 'UserController@save_give_level');

    //赠送付费会员时长
    Route::get('give_level_time/{id}', 'UserController@give_level_time');

    //执行赠送付费会员时长
    Route::put('save_give_level_time/{id}', 'UserController@save_give_level_time');
    //清除会员等级
    Route::delete('del_level/{id}', 'UserController@del_level');
    //编辑其他
    Route::get('edit_other/{id}', 'UserController@edit_other');

    //编辑其他
    Route::put('update_other/{id}', 'UserController@update_other');
    //修改用户状态
    Route::put('set_status/:status/{id}', 'UserController@set_status');
    //获取指定用户的信息
    Route::get('one_info/{id}', 'UserController@oneUserInfo');

    /*会员设置模块*/
    //获取添加会员等级表单
    Route::get('user_level/create', 'UserLevelController@create');

    //添加或修改会员等级
    Route::post('user_level', 'UserLevelController@save');

    //等级详情
    Route::get('user_level/read/{id}', 'UserLevelController@read');
    //获取系统设置的vip列表
    Route::get('user_level/vip_list', 'UserLevelController@get_system_vip_list');
    //删除会员等级
    Route::put('user_level/delete/{id}', 'UserLevelController@delete');
    //设置单个商品上架|下架
    Route::put('user_level/set_show/{id}/{is_show}', 'UserLevelController@set_show');

    //等级列表快速编辑
    Route::put('user_level/set_value/{id}', 'UserLevelController@set_value');
    //等级任务列表
    Route::get('user_level/task/{level_id}', 'UserLevelController@get_task_list');

    //快速编辑等级任务
    Route::put('user_level/set_task/{id}', 'UserLevelController@set_task_value');
    //设置等级任务显示|隐藏
    Route::put('user_level/set_task_show/{id}/{is_show}', 'UserLevelController@set_task_show');
    //设置是否务必达成
    Route::put('user_level/set_task_must/{id}/{is_must}', 'UserLevelController@set_task_must');
    //添加等级任务表单
    Route::get('user_level/create_task', 'UserLevelController@create_task');
    //保存或者修改任务
    Route::post('user_level/save_task', 'UserLevelController@save_task');
    //删除任务
    Route::delete('user_level/delete_task/{id}', 'UserLevelController@delete_task');

    //获取用户分组列表
    Route::get('user_group/list', 'UserGroupController@index');

    //添加修改分组表单
    Route::get('user_group/add/{id}', 'UserGroupController@add');

    //保存分组表单数据
    Route::post('user_group/save', 'UserGroupController@save');

    //删除分组数据
    Route::delete('user_group/del/{id}', 'UserGroupController@delete');

    //设置会员分组
    Route::post('set_group', 'UserController@set_group');
    //执行设置会员分组
    Route::put('save_set_group', 'UserController@save_set_group');
    //会员标签列表
    Route::get('user_label', 'UserLabelController@index');
    //会员标签添加修改表单
    Route::get('user_label/add/{id}', 'UserLabelController@add');
    //保存标签表单数据
    Route::post('user_label/save', 'UserLabelController@save');

    //删除会员标签
    Route::delete('user_label/del/{id}', 'UserLabelController@delete');
    //设置会员分组
    Route::post('set_label', 'UserController@set_label');
    //获取用户标签
    Route::get('label/{uid}', 'UserLabelController@getUserLabel');
    //设置和取消用户标签
    Route::post('label/{uid}', 'UserLabelController@setUserLabel');

    //设置会员分组
    Route::put('save_set_label', 'UserController@save_set_label');
    //标签分类
    Route::get('user_label_cate/all', 'UserLabelCateController@getAll');
    Route::resource('user_label_cate', 'UserLabelCateController');

    //会员卡批次列表资源
    Route::get('member_batch/index', 'MemberCardBatchController@index');
    //添加会员卡批次
    Route::post('member_batch/save/{id}', 'MemberCardBatchController@save');
    //会员卡列表
    Route::get('member_card/index/:card_batch_id', 'MemberCardController@index');
    //会员卡修改状态
    Route::get('member_card/set_status', 'MemberCardController@set_status');
    //列表单字段修改操作
    Route::get('member_batch/set_value/{id}', 'MemberCardBatchController@set_value');

    //会员类型
    Route::get('member/ship', 'MemberCardController@member_ship');
    //会员类型删除
    Route::delete('member_ship/delete/{id}', 'MemberCardController@delete');
    //会员类型修改状态
    Route::get('member_ship/set_ship_status', 'MemberCardController@set_ship_status');

    //会员卡类型编辑
    Route::post('member_ship/save/{id}', 'MemberCardController@ship_save');
    //兑换会员卡二维码
    Route::get('member_scan', 'MemberCardBatchController@member_scan');

    //会员记录
    Route::get('member/record', 'MemberCardController@member_record');
    //会员权益
    Route::get('member/right', 'MemberCardController@member_right');
    //会员权益修改
    Route::post('member_right/save/{id}', 'MemberCardController@right_save');
    //会员协议
    Route::post('member_agreement/save/{id}', 'MemberCardBatchController@save_member_agreement');
    //获取会员协议
    Route::get('member/agreement', 'MemberCardBatchController@getAgreement');
    //用户标签（分类）树形列表
    Route::get('user_tree_label', 'UserLabelController@tree_list');

    /** 用户注销 */
    Route::get('cancel_list', 'UserCancelController@getCancelList');
    Route::post('cancel/set_mark', 'UserCancelController@setMark');
    Route::get('cancel/agree/{id}', 'UserCancelController@agreeCancel');
    Route::get('cancel/refuse/{id}', 'UserCancelController@refuseCancel');

    // 用户白名单管理
    Route::put('user_whitelist/set_field_value/{id}/{value}/{field}', 'UserWhitelistController@setFieldValue');
    Route::get('user_whitelist/{userId}/form', 'UserWhitelistController@userForm');
    Route::post('user_whitelist/add_user', 'UserWhitelistController@addUser');
    Route::get('user_whitelist/import', 'UserWhitelistController@importForm');
    Route::post('user_whitelist/import', 'UserWhitelistController@import');
    Route::delete('user_whitelist', 'UserWhitelistController@batchDel');
    Route::resource('user_whitelist', 'UserWhitelistController');

    // 白名单日志
    Route::get('user_whitelist_log', 'UserWhitelistLogController@index');

    // 用户访问记录
    Route::get('access_log', 'UserAccessLogController@index');

    // 用户意见反馈
    Route::resource('feedback', 'UserFeedbackController');
    Route::put('feedback/set_field_value/{id}/{value}/{field}', 'UserFeedbackController::class@setFieldValue');


});
