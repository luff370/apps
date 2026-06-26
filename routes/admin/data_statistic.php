<?php

/**
 * 数据统计
 */
Route::name('data_statistic.')->prefix('data_statistic')->namespace('DataStatistic')->group(function () {
    Route::get('revenue_report', 'RevenueReportController@index');
    Route::get('revenue_report/export', 'RevenueReportController@export');
    Route::post('revenue_report/recollect', 'RevenueReportController@recollect');
    Route::get('revenue_report/{date}/{appId}', 'RevenueReportController@detail')
        ->where(['date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}', 'appId' => '[0-9]+']);
});
