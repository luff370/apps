<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\AppApiInterfaceService;
use App\Services\App\AppApiObfuscationService;

class ApiObfuscationController extends Controller
{
    public function __construct(
        private AppApiObfuscationService $obfuscationService,
        private AppApiInterfaceService $interfaceService
    ) {
    }

    public function profile()
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['package_name', ''],
        ]);

        return $this->success(
            $this->obfuscationService->getProfile(intval($data['app_id']), (string) $data['package_name'])
        );
    }

    public function saveProfile()
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['package_name', ''],
            ['enabled', 0],
            ['encrypt_request', 0],
            ['encrypt_response', 0],
            ['allow_plaintext_request', 1],
            ['image_url_enabled', 0],
            ['image_domain', ''],
            ['alias_rule', 'hash4'],
            ['request_key_map', '{}'],
            ['response_key_map', '{}'],
            ['response_data_key_map', '{}'],
            ['payload_field', 'payload'],
            ['sign_field', 'sign'],
            ['timestamp_field', 'ts'],
            ['nonce_field', 'nonce'],
            ['version_field', 'ver'],
            ['timestamp_window_seconds', 300],
            ['nonce_ttl_seconds', 300],
            ['cipher', 'AES-256-CBC'],
            ['crypto_key', ''],
            ['crypto_iv', ''],
            ['crypto_sign_key', ''],
            ['image_fields', ''],
            ['image_prefixes', ''],
        ]);

        return $this->success($this->obfuscationService->saveProfile($data), '保存成功');
    }

    public function interfaces()
    {
        $where = $this->getMore([
            ['app_id', 0],
            ['package_name', ''],
            ['keyword', ''],
            ['module', ''],
            ['is_enable', ''],
        ]);
        $data = $this->interfaceService->getAllByPage($where);
        return $this->success($data);
    }

    public function saveInterface()
    {
        $data = $this->getMore([
            ['id', 0],
            ['app_id', 0],
            ['package_name', ''],
            ['name', ''],
            ['module', ''],
            ['path', ''],
            ['method', 'POST'],
            ['alias', ''],
            ['is_enable', 1],
            ['remark', ''],
        ]);

        if (empty($data['path'])) {
            return $this->fail('接口路径不能为空');
        }

        $this->interfaceService->saveOrUpdate($data);
        $this->obfuscationService->saveProfile([
            'app_id' => intval($data['app_id']),
            'package_name' => (string) $data['package_name'],
        ]);

        return $this->success('保存成功');
    }

    public function deleteInterface($id)
    {
        $row = $this->interfaceService->get($id);
        if (empty($row)) {
            return $this->fail('记录不存在');
        }

        $this->interfaceService->delete($id);
        $this->obfuscationService->saveProfile([
            'app_id' => intval($row['app_id']),
            'package_name' => (string) $row['package_name'],
        ]);

        return $this->success('删除成功');
    }

    public function generateAliases()
    {
        $data = $this->getMore([
            ['app_id', 0],
            ['package_name', ''],
            ['rule', 'hash4'],
            ['overwrite', 0],
        ]);

        $result = $this->obfuscationService->generateAliases($data);
        return $this->success($result, '别名生成完成');
    }
}

