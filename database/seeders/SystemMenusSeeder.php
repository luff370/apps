<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 二次开发实际在用的后台菜单。全量覆盖 system_menus。
 */
class SystemMenusSeeder extends Seeder
{
    private array $authToId = [];

    public function run(): void
    {
        $oldAuthById = DB::table('system_menus')->pluck('unique_auth', 'id')->all();
        $roles = DB::table('system_role')->get(['id', 'rules']);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('system_menus')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($this->menus() as $menu) {
            $this->insertNode(0, '', $menu);
        }

        foreach ($roles as $role) {
            $next = [];
            foreach (array_filter(array_map('intval', explode(',', (string) $role->rules))) as $oldId) {
                $auth = $oldAuthById[$oldId] ?? '';
                if ($auth !== '' && isset($this->authToId[$auth])) {
                    $next[] = $this->authToId[$auth];
                }
            }
            $grantedAuth = [];
            foreach ($next as $id) {
                $grantedAuth[] = array_search($id, $this->authToId, true);
            }
            $reportAuths = [
                'data_statistic',
                'data-statistic-revenue-report',
                'data-statistic-recharge-statistics',
                'data-statistic-user-statistics',
            ];
            if (array_intersect($grantedAuth, array_merge(['admin-index-index'], $reportAuths))) {
                foreach ($reportAuths as $auth) {
                    if (isset($this->authToId[$auth])) {
                        $next[] = $this->authToId[$auth];
                    }
                }
            }
            if (in_array('admin-cms', $grantedAuth, true) && isset($this->authToId['cms-article-course'])) {
                $next[] = $this->authToId['cms-article-course'];
            }
            DB::table('system_role')->where('id', $role->id)->update([
                'rules' => implode(',', array_values(array_unique($next))),
            ]);
        }

        Cache::forget('all_auth');
    }

    private function insertNode(int $pid, string $parentPath, array $node): void
    {
        $children = $node['children'] ?? [];
        unset($node['children']);

        $id = (int) DB::table('system_menus')->insertGetId(array_merge([
            'pid' => $pid,
            'icon' => '',
            'module' => 'admin',
            'controller' => '',
            'action' => '',
            'api_url' => '',
            'methods' => '',
            'params' => '[]',
            'sort' => 0,
            'is_show' => 1,
            'is_show_path' => 0,
            'access' => 1,
            'path' => $parentPath,
            'auth_type' => 1,
            'header' => '',
            'is_header' => 0,
            'unique_auth' => '',
            'is_del' => 0,
        ], $node, [
            'pid' => $pid,
            'path' => $parentPath,
        ]));

        if (!empty($node['unique_auth'])) {
            $this->authToId[$node['unique_auth']] = $id;
        }

        $childPath = $parentPath === '' ? (string) $id : $parentPath . '/' . $id;
        foreach ($children as $child) {
            $this->insertNode($id, $childPath, $child);
        }
    }

    private function menus(): array
    {
        return [
            [
                'icon' => 'md-home',
                'menu_name' => '首页',
                'controller' => 'index',
                'sort' => 127,
                'menu_path' => '/admin/home/',
                'header' => 'home',
                'is_header' => 1,
                'unique_auth' => 'admin-index-index',
            ],
            [
                'icon' => 'ios-stats',
                'menu_name' => '报表管理',
                'sort' => 126,
                'menu_path' => '/admin/data_statistic',
                'unique_auth' => 'data_statistic',
                'children' => [
                    ['menu_name' => '营收报表', 'methods' => 'GET', 'sort' => 3, 'menu_path' => '/admin/data_statistic/revenue_report', 'unique_auth' => 'data-statistic-revenue-report'],
                    ['menu_name' => '充值统计', 'methods' => 'GET', 'sort' => 2, 'menu_path' => '/admin/data_statistic/recharge_statistics', 'unique_auth' => 'data-statistic-recharge-statistics'],
                    ['menu_name' => '用户统计', 'methods' => 'GET', 'sort' => 1, 'menu_path' => '/admin/data_statistic/user_statistics', 'unique_auth' => 'data-statistic-user-statistics'],
                ],
            ],
            [
                'icon' => 'md-person',
                'menu_name' => '用户管理',
                'controller' => 'user.user',
                'sort' => 125,
                'menu_path' => '/admin/user',
                'header' => 'user',
                'is_header' => 1,
                'unique_auth' => 'admin-user',
                'children' => [
                    ['menu_name' => '用户管理', 'controller' => 'user.user', 'action' => 'index', 'sort' => 10, 'menu_path' => '/admin/user/list', 'header' => 'user', 'is_header' => 1, 'unique_auth' => 'admin-user-user-index'],
                    [
                        'menu_name' => '用户白名单',
                        'sort' => 8,
                        'menu_path' => '/admin/user/whitelist',
                        'unique_auth' => 'user-whitelist',
                        'children' => [
                            ['menu_name' => '设备白名单', 'menu_path' => '/admin/user/whitelist/device', 'unique_auth' => 'user-whitelist-device'],
                            ['menu_name' => 'ip白名单', 'menu_path' => '/admin/user/whitelist/ip', 'unique_auth' => 'user-whitelist-ip'],
                            ['menu_name' => '区域白名单', 'menu_path' => '/admin/user/whitelist/region', 'unique_auth' => 'user-whitelist-region'],
                            ['menu_name' => '访问日志', 'menu_path' => '/admin/user/whitelist/log', 'unique_auth' => 'admin-whitelist-log'],
                        ],
                    ],
                    ['menu_name' => '访问日志', 'sort' => 6, 'menu_path' => '/admin/user/access_log', 'unique_auth' => 'admin-user-access-log'],
                    ['menu_name' => '意见反馈', 'sort' => 4, 'menu_path' => '/admin/user/feedback', 'unique_auth' => 'user-feedback'],
                    ['menu_name' => '档案管理', 'sort' => 2, 'menu_path' => '/admin/user/archive', 'unique_auth' => 'user-archive'],
                    ['menu_name' => '账号删除申请', 'sort' => 3, 'menu_path' => '/admin/user/deletion_request', 'unique_auth' => 'user-deletion-request'],
                ],
            ],
            [
                'icon' => 'md-cart',
                'menu_name' => '订单管理',
                'controller' => 'order',
                'action' => 'index',
                'sort' => 120,
                'menu_path' => '/admin/order',
                'header' => 'home',
                'is_header' => 1,
                'unique_auth' => 'admin-order',
                'children' => [
                    ['menu_name' => '会员订单', 'sort' => 90, 'menu_path' => '/admin/order/member', 'unique_auth' => 'order-member-list'],
                    ['menu_name' => '订阅管理', 'sort' => 80, 'menu_path' => '/admin/order/subscription', 'unique_auth' => 'admin-order-subscription'],
                ],
            ],
            [
                'icon' => 'logo-usd',
                'menu_name' => '财务管理',
                'controller' => 'finance',
                'sort' => 90,
                'menu_path' => '/admin/finance',
                'header' => 'home',
                'is_header' => 1,
                'unique_auth' => 'admin-finance',
                'children' => [
                    ['menu_name' => '提现申请', 'menu_path' => '/admin/finance/user_withdrawal/index', 'unique_auth' => 'finance-user_withdrawal'],
                ],
            ],
            [
                'icon' => 'ios-book',
                'menu_name' => '内容管理',
                'controller' => 'cms',
                'sort' => 85,
                'menu_path' => '/admin/cms',
                'header' => 'home',
                'is_header' => 1,
                'unique_auth' => 'admin-cms',
                'children' => [
                    ['menu_name' => '内容分类', 'controller' => 'cms.article_category', 'action' => 'index', 'sort' => 10, 'menu_path' => '/admin/cms/article_category/index', 'header' => 'cms', 'is_header' => 1, 'unique_auth' => 'cms-article-category'],
                    [
                        'menu_name' => '内容管理',
                        'controller' => 'cms.article',
                        'action' => 'index',
                        'sort' => 8,
                        'menu_path' => '/admin/cms/article/index',
                        'header' => 'cms',
                        'is_header' => 1,
                        'unique_auth' => 'cms-article-index',
                        'children' => [
                            ['menu_name' => '文章添加', 'controller' => 'cms.article', 'action' => 'add_article', 'is_show' => 0, 'menu_path' => '/admin/cms/article/add_article', 'header' => 'cms', 'is_header' => 1, 'unique_auth' => 'cms-article-creat'],
                        ],
                    ],
                    ['menu_name' => '举报数据', 'sort' => 6, 'menu_path' => '/admin/cms/traffic_violation_content/index', 'unique_auth' => 'cms-traffic_violation_content'],
                    ['menu_name' => '内容爬取', 'sort' => 4, 'is_show' => 0, 'menu_path' => '/admin/cms/generate', 'unique_auth' => 'admin-cms-generate'],
                    ['menu_name' => '课节管理', 'sort' => 2, 'is_show' => 0, 'menu_path' => '/admin/cms/course', 'unique_auth' => 'cms-article-course'],
                ],
            ],
            [
                'icon' => 'md-cube',
                'menu_name' => '应用管理',
                'controller' => 'app',
                'action' => 'index',
                'sort' => 70,
                'menu_path' => '/admin/app',
                'header' => 'app',
                'is_header' => 1,
                'unique_auth' => 'admin-app',
                'children' => [
                    ['menu_name' => '应用列表', 'sort' => 99, 'menu_path' => '/admin/app/apps/index', 'unique_auth' => 'admin-app-apps'],
                    ['menu_name' => 'API混淆管理', 'sort' => 95, 'menu_path' => '/admin/app/obfuscation/index', 'unique_auth' => 'app-obfuscation'],
                    [
                        'menu_name' => '广告管理',
                        'sort' => 92,
                        'menu_path' => '/admin/app/ad_manage',
                        'unique_auth' => 'app-ad-manage',
                        'children' => [
                            ['menu_name' => '广告配置', 'sort' => 99, 'menu_path' => '/admin/app/advertisement', 'unique_auth' => 'app-advertisement'],
                            ['menu_name' => '广告请求明细', 'methods' => 'GET', 'sort' => 2, 'menu_path' => '/admin/app/ad_access_log', 'unique_auth' => 'app-ad-access-log'],
                            ['menu_name' => '广告访问统计', 'methods' => 'GET', 'sort' => 1, 'menu_path' => '/admin/app/ad_access_stat', 'unique_auth' => 'app-ad-access-stat'],
                        ],
                    ],
                    ['menu_name' => '商户管理', 'sort' => 90, 'menu_path' => '/admin/app/merchant', 'unique_auth' => 'app-merchant'],
                    ['menu_name' => '域名管理', 'sort' => 89, 'menu_path' => '/admin/app/domain', 'unique_auth' => 'app-domain'],
                    ['menu_name' => '支付管理', 'sort' => 50, 'menu_path' => '/admin/system/payment', 'unique_auth' => 'admin-system-payment'],
                    ['menu_name' => '素材管理', 'sort' => 30, 'menu_path' => '/admin/system/file', 'unique_auth' => 'system-file'],
                    [
                        'menu_name' => '开发配置',
                        'controller' => 'system',
                        'sort' => 10,
                        'menu_path' => '/admin/system/config',
                        'header' => 'system',
                        'is_header' => 1,
                        'unique_auth' => 'system-config-index',
                        'children' => [
                            ['menu_name' => '配置分类', 'controller' => 'setting.system_config_tab', 'action' => 'index', 'sort' => 99, 'menu_path' => '/admin/system/config/system_config_tab/index', 'header' => 'system', 'is_header' => 1, 'unique_auth' => 'system-config-system_config-tab'],
                            ['menu_name' => '配置数据', 'controller' => 'setting.system_group', 'action' => 'index', 'sort' => 1, 'menu_path' => '/admin/system/config/system_group/index', 'header' => 'system', 'is_header' => 1, 'unique_auth' => 'system-config-system_config-group'],
                            ['menu_name' => '公共API管理', 'menu_path' => '/admin/system/api_interfaces/index', 'unique_auth' => 'system-api-interfaces'],
                        ],
                    ],
                    ['menu_name' => '版本管理', 'sort' => 8, 'menu_path' => '/admin/app/version', 'unique_auth' => 'admin-app-version'],
                    ['menu_name' => '价格配置', 'sort' => 7, 'menu_path' => '/admin/app/product', 'unique_auth' => 'app-product'],
                    ['menu_name' => '协议配置', 'sort' => 6, 'menu_path' => '/admin/app/agreements', 'unique_auth' => 'app-agreements'],
                    ['menu_name' => '支付配置', 'sort' => 5, 'menu_path' => '/admin/app/payments', 'unique_auth' => 'app-payments'],
                    ['menu_name' => '参数配置', 'sort' => 4, 'menu_path' => '/admin/app/config/index', 'unique_auth' => 'app-config'],
                ],
            ],
            [
                'icon' => 'md-settings',
                'menu_name' => '系统设置',
                'controller' => 'setting.system_config',
                'action' => 'index',
                'sort' => 0,
                'menu_path' => '/admin/setting',
                'header' => 'setting',
                'is_header' => 1,
                'unique_auth' => 'admin-setting',
                'children' => [
                    ['menu_name' => '系统设置', 'controller' => 'setting.system_config', 'action' => 'index', 'sort' => 10, 'menu_path' => '/admin/setting/system_config', 'header' => 'setting', 'is_header' => 1, 'unique_auth' => 'setting-system-config'],
                    [
                        'menu_name' => '管理权限',
                        'controller' => 'setting.system_admin',
                        'sort' => 1,
                        'menu_path' => '/admin/setting/auth/list',
                        'header' => 'setting',
                        'is_header' => 1,
                        'unique_auth' => 'setting-system-admin',
                        'children' => [
                            ['menu_name' => '角色管理', 'controller' => 'setting.system_role', 'action' => 'index', 'sort' => 3, 'menu_path' => '/admin/setting/system_role/index', 'header' => 'setting', 'is_header' => 1, 'unique_auth' => 'setting-system-role'],
                            ['menu_name' => '管理员列表', 'controller' => 'setting.system_admin', 'action' => 'index', 'sort' => 2, 'menu_path' => '/admin/setting/system_admin/index', 'header' => 'setting', 'unique_auth' => 'setting-system-list'],
                            ['menu_name' => '权限菜单', 'controller' => 'setting.system_menus', 'action' => 'index', 'sort' => 1, 'menu_path' => '/admin/setting/system_menus/index', 'header' => 'setting', 'is_header' => 1, 'unique_auth' => 'setting-system-menus'],
                        ],
                    ],
                    ['menu_name' => '个人中心', 'menu_path' => '/admin/system/user', 'unique_auth' => 'system-user'],
                ],
            ],
        ];
    }
}
