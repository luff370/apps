<?php

namespace App\Services\App;

use App\Services\Service;
use App\Dao\App\AppApiInterfaceDao;

class AppApiInterfaceService extends Service
{
    public function __construct(AppApiInterfaceDao $dao)
    {
        $this->dao = $dao;
    }

    public function saveOrUpdate(array $data): void
    {
        $id = intval($data['id'] ?? 0);
        $save = [
            'app_id' => intval($data['app_id'] ?? 0),
            'package_name' => (string) ($data['package_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'module' => (string) ($data['module'] ?? ''),
            'path' => ltrim((string) ($data['path'] ?? ''), '/'),
            'method' => strtoupper((string) ($data['method'] ?? 'POST')),
            'alias' => (string) ($data['alias'] ?? ''),
            'is_enable' => intval($data['is_enable'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ];

        if ($id > 0) {
            $this->dao->update($id, $save);
            return;
        }
        $this->dao->save($save);
    }
}

