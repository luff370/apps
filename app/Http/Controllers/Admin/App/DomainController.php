<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\DomainService;

/**
 * 域名管理
 */
class DomainController extends Controller
{
    public function __construct(DomainService $service)
    {
        $this->service = $service;
    }

    /**
     * 数据列表
     */
    public function index()
    {
        $filter = $this->getMore([
            ['keyword', ''],
            ['status', ''],
            ['risk_level', ''],
        ]);
        $data = $this->service->getAllByPage($filter, ['*'], ['id' => 'desc']);

        return $this->success($data);
    }

    /**
     * 保存新建
     */
    public function store()
    {
        $this->service->saveDomain($this->getFormData());

        return $this->success(100021);
    }

    /**
     * 数据更新
     */
    public function update($id)
    {
        $data = $this->getFormData();
        $data['id'] = $id;
        $this->service->saveDomain($data);

        return $this->success(100001);
    }

    /**
     * 删除数据
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success(100002);
    }

    /**
     * 根据id修改指定字段值
     */
    public function setFieldValue($id, $value, $field)
    {
        if (!$id = intval($id)) {
            return $this->fail(100100);
        }
        $allowFields = ['status', 'risk_level'];
        if (!in_array($field, $allowFields, true)) {
            return $this->fail(100100);
        }
        if ($field === 'risk_level') {
            $value = $this->service->normalizeRiskLevel($value);
        } else {
            $value = (int) $value === 1 ? 1 : 0;
        }
        $this->service->update($id, [$field => $value]);

        return $this->success(100014);
    }

    private function getFormData(): array
    {
        return $this->getMore([
            ['id', 0],
            ['domain', ''],
            ['subject', ''],
            ['expire_at', ''],
            ['status', 1],
            ['risk_level', 1],
            ['remark', ''],
        ]);
    }
}
