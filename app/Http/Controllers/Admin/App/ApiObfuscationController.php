<?php

namespace App\Http\Controllers\Admin\App;

use App\Http\Controllers\Admin\Controller;
use App\Services\App\AppApiObfuscationService;

class ApiObfuscationController extends Controller
{
    public function __construct(private AppApiObfuscationService $obfuscationService) {}

    public function profile()
    {
        $data = $this->getMore([['app_id', 0], ['package_name', '']]);
        return $this->success($this->obfuscationService->getProfile(intval($data['app_id']), (string) $data['package_name']));
    }

    public function saveProfile()
    {
        $data = $this->getMore([
            ['app_id', 0], ['package_name', ''], ['enabled', 0], ['encrypt_request', 0], ['encrypt_response', 0], ['allow_plaintext_request', 1], ['image_url_enabled', 0], ['image_domain', ''], ['alias_rule', 'hash4'], ['request_key_map', '{}'], ['response_key_map', '{}'], ['response_data_key_map', '{}'], ['payload_field', 'payload'], ['sign_field', 'sign'], ['timestamp_field', 'ts'], ['nonce_field', 'nonce'], ['version_field', 'ver'], ['timestamp_window_seconds', 300], ['nonce_ttl_seconds', 300], ['cipher', 'AES-256-CBC'], ['crypto_key', ''], ['crypto_iv', ''], ['crypto_sign_key', ''], ['image_fields', ''], ['image_prefixes', ''],
        ]);
        return $this->success($this->obfuscationService->saveProfile($data), '保存成功');
    }

    public function aliases()
    {
        $where = $this->getMore([['app_id', 0], ['package_name', ''], ['keyword', '']]);
        return $this->success($this->obfuscationService->aliases($where));
    }

    public function saveAlias()
    {
        $data = $this->getMore([['id', 0], ['app_id', 0], ['package_name', ''], ['interface_id', 0], ['alias', ''], ['request_key_map', []], ['response_key_map', []], ['response_data_key_map', []], ['is_enable', 1], ['remark', '']]);
        if (empty($data['interface_id'])) return $this->fail('请选择公共接口');
        $this->obfuscationService->saveAlias($data);
        return $this->success('保存成功');
    }

    public function deleteAlias($id)
    {
        $this->obfuscationService->deleteAlias((int) $id);
        return $this->success('删除成功');
    }

    public function generateAliases()
    {
        $data = $this->getMore([['app_id', 0], ['package_name', ''], ['rule', 'hash4'], ['map_rule', 'short'], ['overwrite', 0]]);
        return $this->success($this->obfuscationService->generateAliases($data), '别名生成完成');
    }

    public function generateDefaults()
    {
        $data = $this->getMore([['map_rule', 'short']]);
        return $this->success($this->obfuscationService->generateDefaultProfileFields($data));
    }

    public function previewAlias($id)
    {
        $data = $this->obfuscationService->previewAlias((int) $id);
        if (!$data) return $this->fail('别名配置不存在');
        return $this->success($data);
    }

    public function exportAliases()
    {
        $data = $this->getMore([['app_id', 0], ['package_name', '']]);
        return $this->success($this->obfuscationService->exportAliases($data));
    }

    public function exportProfile()
    {
        $data = $this->getMore([['app_id', 0], ['package_name', '']]);
        return $this->success($this->obfuscationService->exportProfile($data));
    }
}
