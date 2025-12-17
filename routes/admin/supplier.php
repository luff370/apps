<?php

/**
 * 供应商管理 相关路由
 */
Route::name('supplier')->prefix('supplier')->namespace('Supplier')->group( function () {
    // 供应商资源路由
    Route::resource('supplier', 'SupplierController');
    // 修改状态
    Route::put('supplier/set_status/{id}/{status}', 'SupplierController@set_status');
    // 设置退回收货地址
    Route::get('supplier/{id}/return_back_address', 'SupplierController@returnBackAddressForm');
    Route::put('supplier/{id}/return_back_address', 'SupplierController@setReturnBackAddress');
});
