<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 应用基础信息
Route::post('app/info', 'CommonController@appInfo');
// 获取配置数据
Route::post('common/get_group_data/{name}', 'CommonController@getGroupData');
// 提现成功用户展示
Route::post('common/withdrawal_users_show', 'CommonController@withdrawalUsersShow');
// 用户设备token上传
Route::post('common/upload_device_token', 'CommonController@saveDeviceToken');
// 文件上传
Route::middleware(['token_auth'])->post('common/upload', 'CommonController@fileUpload');

// 支付相关
Route::prefix('payment')->group(function (\Illuminate\Routing\Router $route) {
    // 支付通知
    $route->post('/wechat/{id}/notify', 'PayCallbackController@wechatNotify');
    $route->post('/alipay/{id}/notify', 'PayCallbackController@alipayNotify');
    $route->post('/apple/notify', 'PayCallbackController@appleNotify');
    // 支付返回跳转
    $route->get('/return', 'PaymentController@payReturn');
    // 支付测试
    $route->post('/test', 'PaymentController@test');
    // 支付状态
    $route->post('order/status', 'PaymentController@orderStatus');

    // 订单支付，同步验证
    $route->middleware(['token_auth'])->group(function (\Illuminate\Routing\Router $route) {
        // 订单支付
        $route->post('order', 'PaymentController@orderPay');

        // 支付验证
        $route->post('/apple/verify', 'PayCallbackController@applePayVerify');
        $route->post('/google/verify', 'PayCallbackController@googlePayVerify');
    });
});

Route::group(['prefix' => 'auth'], function (\Illuminate\Routing\Router $route) {
    // 唯一标识uuid登录
    $route->post('login_by_uuid', 'AuthController@loginByUuid');
    $route->post('reg_by_account', 'AuthController@regByAccount');
    $route->post('login_by_account', 'AuthController@loginByAccount');
    $route->post('login_by_facebook', 'AuthController@loginByFacebook');
    $route->post('login_by_google', 'AuthController@loginByGoogle');
    $route->post('login_by_apple', 'AuthController@loginByApple');

    $route->post('alipay', 'AuthController@alipayAuth');
});

Route::prefix('user')->middleware(['token_auth'])->group(
    function (\Illuminate\Routing\Router $route) {
        // 用户详情接口
        $route->post('info', 'UserController@info');
        // 意见反馈
        $route->post('feedback', 'UserController@feedback');
        // 退出账号
        $route->post('logout', 'UserController@logout');
        // 注销账号
        $route->post('sign_out', 'UserController@singOut');
        // 用户设备信息更新
        $route->post('save_device_info', 'UserController@deviceInfoUpdate');

        // 用户提现操作
        Route::prefix('withdrawal')->group(
            function (\Illuminate\Routing\Router $route) {
                // 用户提现记录
                $route->post('products', 'UserWithdrawalController@products');
                // 用户提现记录
                $route->post('records', 'UserWithdrawalController@list');
                // 用户提现申请
                $route->post('application', 'UserWithdrawalController@application');
            }
        );
    });

    Route::post('coin/packages', 'UserWithdrawalController@products');

Route::prefix('content')->group(
    function (\Illuminate\Routing\Router $route) {
        // 内容分类列表
        $route->post('cate', 'ContentController@cate');
        // 内容列表
        $route->post('list', 'ContentController@list');
        // 分类文章列表
        $route->post('listByCate', 'ContentController@listByCate');
        // 内容详情
        $route->post('detail', 'ContentController@detail');
        // 搜索热词
        $route->post('hot_words', 'ContentController@hotWords');
    });

Route::prefix('favorites')->middleware(['token_auth'])->group(
    function (\Illuminate\Routing\Router $route) {
        // 内容列表
        $route->post('list', 'FavoritesController@list');
        // 收藏
        $route->post('collect', 'FavoritesController@collect');
        // 取消收藏
        $route->post('cancel', 'FavoritesController@cancel');
    });

Route::prefix('member')->middleware(['token_auth'])->group(
    function (\Illuminate\Routing\Router $route) {
        // 会员权益信息
        $route->post('info', 'MemberController@info');
        // 会员列表
        $route->post('list', 'MemberController@list');
        // 购买、订阅
        $route->post('order', 'MemberController@order');
    });


Route::prefix('task')->middleware(['token_auth'])->group(
    function (\Illuminate\Routing\Router $route) {
        // 获取任务状态
        $route->post('status', 'TaskController@getStatus');
        // 完成任务
        $route->post('completed', 'TaskController@completed');
    });

Route::prefix('ad')->group(
    function (\Illuminate\Routing\Router $route) {
        // 广告列表
        $route->post('list', 'AdvertisementController@list');
    });

Route::prefix('chatAI')->middleware(['token_auth'])->group(
    function (\Illuminate\Routing\Router $route) {
        // Chat AI 对话
        $route->post('dialogue', 'ChatAiController@task');
        // 对话内容评价
        $route->post('content/evaluate', 'ChatAiController@evaluate');
        // 图生图
        $route->post('imageToImage', 'ChatAiController@imageToImage');
        // 获取生成的图片
        $route->post('getImages', 'ChatAiController@getImages');
        // 图像识别
        $route->post('imageRecognize', 'ChatAiController@imageRecognize');
    });

// 违章举报内容
Route::prefix('trafficViolation')->group(
    function (\Illuminate\Routing\Router $route) {
        // 违章曝光列表
        $route->post('list', 'TrafficViolationController@list');
        // 违章曝光详情
        $route->post('details', 'TrafficViolationController@details');
        // 交通标志
        $route->post('signs', 'TrafficViolationController@signs');

        Route::middleware(['token_auth'])->group(function (\Illuminate\Routing\Router $route) {
            $route->post('save', 'TrafficViolationController@save');
            $route->post('user/records', 'TrafficViolationController@userRecords');
            $route->post('user/details', 'TrafficViolationController@userDetails');
            $route->post('user/getRewards', 'TrafficViolationController@getRewards');
        });
    });

// 用户统计路由组
Route::prefix('user/stat')->group(
    function (\Illuminate\Routing\Router $route) {
        // 用户活跃统计
        $route->post('active', 'UserStatisticsController@userActiveStat');
    }
);

