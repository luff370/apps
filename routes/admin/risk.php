<?php

/**
 * 风控管理
 */
Route::name('risk')->prefix('risk')->namespace('Risk')->group(function () {
    Route::get('overview', 'RiskController@overview');

    Route::get('device', 'RiskController@devices');
    Route::get('device/{id}/graph', 'RiskController@deviceGraph');
    Route::get('device/{id}', 'RiskController@deviceDetail');

    Route::get('event', 'RiskController@events');
    Route::get('graph/clusters', 'RiskController@clusters');

    Route::get('strategy', 'RiskController@strategies');
    Route::get('strategy/create', 'RiskController@strategyCreate');
    Route::put('strategy/set_field_value/{id}', 'RiskController@strategySetField');
    Route::get('strategy/{id}/edit', 'RiskController@strategyEdit');
    Route::post('strategy', 'RiskController@strategyStore');
    Route::put('strategy/{id}', 'RiskController@strategyUpdate');
    Route::delete('strategy/{id}', 'RiskController@strategyDestroy');

    Route::get('list', 'RiskController@lists');
    Route::get('list/create', 'RiskController@listCreate');
    Route::put('list/set_field_value/{id}', 'RiskController@listSetField');
    Route::get('list/{id}/edit', 'RiskController@listEdit');
    Route::post('list', 'RiskController@listStore');
    Route::put('list/{id}', 'RiskController@listUpdate');
    Route::delete('list/{id}', 'RiskController@listDestroy');
});
