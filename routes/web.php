<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/article/{id}', 'CommonController@article');

Route::get('/agreement/{type}/{app_id}/{platform}', 'CommonController@appAgreement');
Route::get('/agreement/{type}/{app_id}/{platform}/{version}', 'CommonController@appAgreement');

Route::get('/alipay_auth_callback', 'CallbackController@alipayAuth');
