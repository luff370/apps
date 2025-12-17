<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// 用户名密码登录
Route::post('login', 'AuthController@login')->name('admin.login');
// 后台登录页面数据
Route::get('login/info', 'AuthController@info')->name('admin.info');
//退出登陆
Route::get('setting/admin/logout', 'AuthController@logout')->name('admin.logout');
//验证码
Route::get('captcha_pro', 'AuthController@captcha')->name('admin.captcha');
//获取验证码
Route::get('ajcaptcha', 'AuthController@ajcaptcha')->name('ajcaptcha');
//一次验证
Route::post('ajcheck', 'AuthController@ajcheck')->name('ajcheck');

// 自动发货相关接口
Route::post('order/wait_deliver_export', 'OutApiController@waitDeliverOrder');
Route::post('order/deliver_import', 'OutApiController@orderDeliverImport');
Route::post('order/deliver_status_change', 'OutApiController@orderStatusChangeDeliver');
// 支付商户号投诉查询
Route::get('queryPaymentComplaints', 'OutApiController@queryPaymentComplaints');
// 获取近期下单用户手机号
Route::get('getPaidOrderUserPhone', 'OutApiController@getPaidOrderUserPhone');
// 通道公司列表
Route::get('channelCompanyList', 'OutApiController@channelCompanyList');
// 创建短链
Route::post('createShortLink', 'OutApiController@createShortLink');
// 设置推送成功条数
Route::post('setPushCount', 'OutApiController@setPushCount');
// 获取系统服务通知
Route::get('getServiceNotice', 'OutApiController@getServiceNotice');
// 通知成功回调
Route::get('notificationSuccess', 'OutApiController@notificationSuccess');

// 测试接口
Route::any('test/{action}', 'TestController@index');


// 登录验证路由
Route::name('admin.')->middleware([
    'admin.auth',
    'admin.operation_log',
])->group(function () {
    require_once base_path("/routes/admin/common.php");
    require_once base_path("/routes/admin/distribution.php");
    require_once base_path("/routes/admin/file.php");
    require_once base_path("/routes/admin/finance.php");
    require_once base_path("/routes/admin/freight.php");
    require_once base_path("/routes/admin/order.php");
    require_once base_path("/routes/admin/cms.php");
    require_once base_path("/routes/admin/setting.php");
    require_once base_path("/routes/admin/statistic.php");
    require_once base_path("/routes/admin/app.php");
    require_once base_path("/routes/admin/system.php");
    require_once base_path("/routes/admin/user.php");
});

