<?php

namespace App\Support\Utils;

use App\Models\SystemAdmin;

/**
 * Class Auth
 *
 * @package App\Http\controllers
 */
class Auth
{
    public static $search = [];

    /**
     * 生成管理员资源权限
     *
     * @param bool $subPrivilege
     *
     * @return array
     */
    public static function search(bool $subPrivilege = false, string $key = null): array
    {
        return [];

        if (isset(self::$search[$subPrivilege])) {
            return self::$search[$subPrivilege];
        }

        $search = [];
        switch (adminType()) {
            case SystemAdmin::AccountTypeService: // 客服
                $key = $key ?? 'spread_uid';
                $search[$key] = adminId();
                break;
            case SystemAdmin::AccountTypeSupplier: // 供应商
                $key = $key ?? 'supplier_id';
                $search[$key] = adminId();
                break;
            case SystemAdmin::AccountTypeAgent: // 代理商
                $key = $key ?? 'spread_uid';
                $agentId = adminInfo()['agent']['id'];
                $search[$key] = $agentId;


                break;
        }

        self::$search[$subPrivilege] = $search;

        return $search;
    }
}
