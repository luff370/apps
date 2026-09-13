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

$deviceEnv = ['device_env_risk'];

// 支付回调、同步跳转：来自微信/支付宝/苹果，不解密 Device-Env。
Route::prefix('payment')->group(function (\Illuminate\Routing\Router $route) {
    $route->post('/wechat/{id}/notify', 'PayCallbackController@wechatNotify');
    $route->post('/alipay/{id}/notify', 'PayCallbackController@alipayNotify');
    $route->post('/apple/notify', 'PayCallbackController@appleNotify');
    $route->get('/return', 'PaymentController@payReturn');
});

// 用户行为上报是独立 JSON 协议，不读取 Device-Env。
Route::middleware(['token_auth'])->post('user/behavior/report', 'UserBehaviorController@report');

// 阅读任务完成回调来自渠道，不解密 Device-Env。
Route::any('read_task/completed/{ch}', 'ReadTaskController@completedTaskCallback');

Route::middleware($deviceEnv)->group(function () {
    // 应用基础信息
    Route::post('app/info', 'CommonController@appInfo');
    // 应用版本更新
    Route::get('app/update', 'CommonController@appUpdate');
    // 获取配置数据
    Route::post('common/get_group_data/{name}', 'CommonController@getGroupData');
    // 提现成功用户展示
    Route::post('common/withdrawal_users_show', 'CommonController@withdrawalUsersShow');
    // 用户设备token上传
    Route::post('common/upload_device_token', 'CommonController@saveDeviceToken');
    // 文件上传
    Route::middleware(['token_auth'])->post('common/upload', 'CommonController@fileUpload');

    Route::prefix('payment')->group(function (\Illuminate\Routing\Router $route) {
        // 获取当前应用下已开启的支付通道
        $route->post('channels', 'PaymentController@availableChannels');
        // 支付测试
        $route->post('/test', 'PaymentController@test');
        // 支付状态
        $route->post('order/status', 'PaymentController@orderStatus');

        // 订单支付，同步验证
        $route->middleware(['token_auth'])->group(function (\Illuminate\Routing\Router $route) {
            $route->post('order', 'PaymentController@orderPay');
            $route->post('/apple/verify', 'PayCallbackController@applePayVerify');
            $route->post('/google/verify', 'PayCallbackController@googlePayVerify');
        });
    });

    Route::group(['prefix' => 'auth'], function (\Illuminate\Routing\Router $route) {
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
            $route->post('info', 'UserController@info');
            $route->post('feedback', 'UserController@feedback');
            $route->post('feedback/list', 'UserController@feedbackList');
            $route->post('logout', 'UserController@logout');
            $route->post('sign_out', 'UserController@singOut');
            $route->post('save_device_info', 'UserController@deviceInfoUpdate');

            Route::prefix('withdrawal')->group(
                function (\Illuminate\Routing\Router $route) {
                    $route->post('products', 'UserWithdrawalController@products');
                    $route->post('records', 'UserWithdrawalController@list');
                    $route->post('application', 'UserWithdrawalController@application');
                }
            );
        });

    Route::prefix('user')->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('profile', 'UserController@profile');
        });

    Route::post('coin/packages', 'UserWithdrawalController@products');

    Route::prefix('content')->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('cate', 'ContentController@cate');
            $route->post('list', 'ContentController@list');
            $route->post('listByCate', 'ContentController@listByCate');
            $route->post('detail', 'ContentController@detail');
            $route->post('hot_words', 'ContentController@hotWords');
        });

    Route::prefix('favorites')->middleware(['token_auth'])->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('list', 'FavoritesController@list');
            $route->post('collect', 'FavoritesController@collect');
            $route->post('cancel', 'FavoritesController@cancel');
        });

    Route::prefix('member')->middleware(['token_auth'])->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('info', 'MemberController@info');
            $route->post('list', 'MemberController@list');
            $route->post('order', 'MemberController@order');
        });

    Route::prefix('task')->middleware(['token_auth'])->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('status', 'TaskController@getStatus');
            $route->post('completed', 'TaskController@completed');
        });

    Route::prefix('ad')->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('list', 'AdvertisementController@list');
            $route->post('stat', 'AdvertisementController@stat');
        });

    Route::prefix('chatAI')->middleware(['token_auth'])->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('dialogue', 'ChatAiController@task');
            $route->post('content/evaluate', 'ChatAiController@evaluate');
            $route->post('imageToImage', 'ChatAiController@imageToImage');
            $route->post('getImages', 'ChatAiController@getImages');
            $route->post('imageRecognize', 'ChatAiController@imageRecognize');
        });

    Route::prefix('trafficViolation')->group(
        function (\Illuminate\Routing\Router $route) {
            $route->post('list', 'TrafficViolationController@list');
            $route->post('details', 'TrafficViolationController@details');
            $route->post('signs', 'TrafficViolationController@signs');

            Route::middleware(['token_auth'])->group(function (\Illuminate\Routing\Router $route) {
                $route->post('save', 'TrafficViolationController@save');
                $route->post('user/records', 'TrafficViolationController@userRecords');
                $route->post('user/details', 'TrafficViolationController@userDetails');
                $route->post('user/getRewards', 'TrafficViolationController@getRewards');
            });
        });

    Route::post('read_task/get', 'ReadTaskController@getReadTask');

    // 混淆网关入口。旧版 /api/open/{alias} 与新版 /api/open/atlasriver/{alias} 靠路径段数区分，
    // 不使用 {params?}，避免旧路由把 gatewaySuffix 误当成 alias。
    foreach (config('api_obfuscation.gateway_prefixes', ['gateway']) as $gatewayPrefix) {
        $p = trim($gatewayPrefix, '/');

        Route::any("{$p}/{gatewaySuffix}/{alias}", 'ObfuscatedGatewayController@dispatchDynamic')
            ->where('gatewaySuffix', '[a-z]{6,63}')
            ->where('alias', '[a-z0-9]{8}');

        Route::any("{$p}/{alias}", 'ObfuscatedGatewayController@dispatch')
            ->where('alias', '[a-z0-9]{4,32}');
    }
});
