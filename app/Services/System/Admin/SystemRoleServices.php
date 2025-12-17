<?php

namespace App\Services\System\Admin;

use App\Services\Service;
use Illuminate\Http\Request;
use App\Exceptions\AuthException;
use App\Models\SystemMenu;
use Illuminate\Support\Facades\Cache;
use App\Dao\System\Admin\SystemRoleDao;
use App\Services\System\SystemMenuServices;

/**
 * Class SystemRoleServices
 *
 * @package App\Services\System\admin
 * @method update($id, array $data, ?string $key = null) 修改数据
 * @method save(array $data) 保存数据
 * @method get(int $id, ?array $field = []) 获取数据
 * @method delete(int $id, ?string $key = null) 删除数据
 */
class SystemRoleServices extends Service
{
    /**
     * 当前管理员权限缓存前缀
     */
    const ADMIN_RULES_LEVEL = 'Admin_rules_level_';

    /**
     * SystemRoleServices constructor.
     *
     * @param SystemRoleDao $dao
     */
    public function __construct(SystemRoleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取权限
     *
     * @return mixed
     */
    public function getRoleArray(array $where = [], string $field = '', string $key = '')
    {
        return $this->dao->getRoule($where, $field, $key);
    }

    /**
     * 获取表单所需的权限名称列表
     *
     * @param int $level
     *
     * @return array
     */
    public function getRoleFormSelect(int $level)
    {
        $list = $this->getRoleArray(['level' => $level, 'status' => 1]);
        $options = [];
        foreach ($list as $id => $roleName) {
            $options[] = ['label' => $roleName, 'value' => $id];
        }

        return $options;
    }

    /**
     * 身份管理列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getRoleList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getRouleList($where, $page, $limit);
        $count = $this->dao->count($where);
        /** @var SystemMenuServices $service */
        $service = app(SystemMenuServices::class);
        foreach ($list as &$item) {
            $item['rules'] = implode(',', array_merge($service->getColumn(['id' => $item['rules']], 'menu_name', 'id')));
        }

        return compact('count', 'list');
    }

    /**
     * 后台验证权限
     *
     * @param Request $request
     *
     * @return bool|void
     * @throws \throwable
     */
    public function verifyAuth(Request $request)
    {
        // 获取当前的接口于接口类型
        $rule = trim(strtolower($request->getRequestUri()));
        $method = trim(strtolower($request->method()));

        // 判断接口是一下两种的时候放行
        if (in_array($rule, ['setting/admin/logout', 'menuslist'])) {
            return true;
        }

        // 获取所有接口类型以及对应的接口
        $allAuth = Cache::remember('all_auth', now()->addDays(30), function () {
            $allList = SystemMenu::query()
                ->where('api_url', '!=', '')
                ->where('auth_type', '=', 2)
                ->get(['api_url', 'methods']);

            $allAuth = [];
            foreach ($allList as $item) {
                $allAuth[trim(strtolower($item['methods']))][] = trim(strtolower(str_replace(' ', '', $item['api_url'])));
            }

            return $allAuth;
        });

        // 权限菜单未添加时放行
        if (!in_array($rule, $allAuth[$method])) {
            return true;
        }

        // 获取管理员的接口权限列表，存在时放行
        $auth = $this->getRolesByAuth($request->adminInfo()['roles'], 2);
        if (in_array($rule, $auth[$method])) {
            return true;
        } else {
            throw new AuthException(110000);
        }
    }

    /**
     * 获取指定权限
     *
     * @param array $rules
     * @param int $type
     * @param string $cachePrefix
     *
     * @return array|mixed
     * @throws \throwable
     */
    public function getRolesByAuth(array $rules, int $type = 1, string $cachePrefix = self::ADMIN_RULES_LEVEL)
    {
        if (empty($rules)) {
            return [];
        }
        $cacheName = md5($cachePrefix . '_' . $type . '_' . implode('_', $rules));

        return Cache::remember($cacheName, now()->addDays(30), function () use ($rules, $type) {
            /** @var SystemMenuServices $menusServices */
            $menusServices = app(SystemMenuServices::class);
            $authList = $menusServices->getColumn([['id', 'IN', $this->getRoleIds($rules)], ['auth_type', '=', $type]], 'api_url,methods');
            $rolesAuth = [];
            foreach ($authList as $item) {
                $rolesAuth[trim(strtolower($item['methods']))][] = trim(strtolower(str_replace(' ', '', $item['api_url'])));
            }

            return $rolesAuth;
        });
    }

    /**
     * 获取权限id
     *
     * @param array $rules
     *
     * @return array
     */
    public function getRoleIds(array $rules)
    {
        $rules = $this->dao->getColumn([['id', 'IN', $rules], ['status', '=', '1']], 'rules', 'id');

        return array_unique(explode(',', implode(',', $rules)));
    }
}
