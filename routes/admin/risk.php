<?php

/**
 * 风控管理
 */
Route::name('risk')->prefix('risk')->namespace('Risk')->group(function () {
    Route::get('overview', 'RiskController@overview');

    Route::get('device', 'RiskController@devices');
    Route::get('device/{id}/graph', 'RiskController@deviceGraph');
    Route::get('device/{id}', 'RiskController@deviceDetail');

    Route::get('event/count', 'RiskController@eventCount');
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

    Route::get('probability/summary', 'RiskLedgerController@summary');
    Route::get('probability/trends', 'RiskLedgerController@trends');
    Route::get('probability/app-ranking', 'RiskLedgerController@ranking');

    Route::get('releases', 'RiskLedgerController@releaseList');
    Route::get('release-rules', 'RiskLedgerController@rules');
    Route::get('releases/{id}', 'RiskLedgerController@releaseDetail');
    Route::post('releases/{id}/revoke', 'RiskLedgerController@revoke');

    Route::get('ip-dossiers', 'RiskLedgerController@dossiers');
    Route::get('ip-dossiers/options', 'RiskLedgerController@dossierOptions');
    Route::get('ip-dossiers/{id}', 'RiskLedgerController@dossier');
    Route::get('ip-dossiers/{id}/relations', 'RiskLedgerController@relations');
    Route::get('ip-dossiers/{id}/timeline', 'RiskLedgerController@timeline');

    Route::get('monitor-accounts', 'RiskLedgerController@monitorAccounts');
    Route::post('monitor-accounts', 'RiskLedgerController@storeMonitorAccount');
    Route::match(['post', 'patch'], 'monitor-accounts/{id}', 'RiskLedgerController@updateMonitorAccount');
    Route::delete('monitor-accounts/{id}', 'RiskLedgerController@destroyMonitorAccount');

    Route::get('publishing-monitor', 'RiskLedgerController@publishingBoard');
});
