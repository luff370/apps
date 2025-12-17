<?php

return [

    /*
     * 站点标题
     */
    'name' => '管理后台',

    /*
    * Admin 域名
    */
    'url' => env('ADMIN_URL','http://storeapi.appasd.com'),

    /*
     * 路由配置
     */
    'route' => [
        // 路由前缀
        'prefix' => env('ADMIN_ROUTE_PREFIX', 'admin'),
        // 控制器命名空间前缀
        'namespace' => 'App\\Http\\Controllers\\Admin',
        // 默认中间件列表
        'middleware' => ['web', 'admin'],
    ],

    /*
     * 前端项目路径
     */
    'view_path' => '/Users/mac/code/web/apps-admin/src/',
];
