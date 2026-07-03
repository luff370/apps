<?php

namespace App\Services\App;

use App\Dao\App\MerchantDao;
use App\Exceptions\AdminException;
use App\Models\Merchant;
use App\Services\Service;
use App\Support\Services\FormBuilder as Form;
use App\Support\Services\FormOptions;

/**
 * Class MerchantService
 */
class MerchantService extends Service
{
    /**
     * MerchantService constructor.
     */
    public function __construct(MerchantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 列表数据处理
     */
    public function tidyListData($list)
    {
        $typeNameMap = Merchant::typeNameMap();
        foreach ($list as &$item) {
            $item['type_name'] = $typeNameMap[$item['type']] ?? '';
            $item['domain_expire_at'] = $item['domain_expired_date'] ?? '';
            $item['agreement_templates'] = $item['agreement_templates'] ?: [];
        }

        return $list;
    }

    /**
     * 新增表单获取
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm(): array
    {
        return [
            'agreement_templates' => [],
            'is_enable' => 1,
        ];
    }


    /**
     * 编辑表单获取
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function updateForm(int $id): array
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $data = $info->toArray();
        $data['domain_expire_at'] = $data['domain_expired_date'] ?? '';
        $data['agreement_templates'] = $data['agreement_templates'] ?: [];

        return $data;
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $f[] = Form::text('name', '公司名称', $info['name'] ?? '')->required();
        $f[] = Form::text('domain', '商户域名', $info['domain'] ?? '')->required();
        $f[] = Form::date('domain_expired_date', '域名到期时间', $info['domain_expired_date'] ?? '')->required();
        $f[] = Form::select('type', '企业类型', $info['type'] ?? 1)->options(FormOptions::toFormOptions(Merchant::typeNameMap()));
        $f[] = Form::text('corporate', '企业法人', $info['corporate'] ?? '');
        $f[] = Form::textarea('registered_address', '注册地址', $info['registered_address'] ?? '');

        return $f;
    }

    /**
     * 商户新增/编辑统一入口，兼容新页面直接 POST id 的保存方式。
     */
    public function saveMerchant(array $data)
    {
        $saveData = $this->normalizeSaveData($data);
        if (!empty($data['id'])) {
            return $this->update((int)$data['id'], $saveData);
        }

        return $this->dao->newQuery()->create($saveData);
    }

    /**
     * 将前端新增字段映射到旧表字段，并把协议母版规范成可直接落库的 JSON。
     */
    public function normalizeSaveData(array $data): array
    {
        $templates = $this->normalizeAgreementTemplates($data['agreement_templates'] ?? []);

        return [
            'name' => (string)($data['name'] ?? ''),
            'domain' => (string)($data['domain'] ?? ''),
            'domain_expired_date' => (string)($data['domain_expire_at'] ?? $data['domain_expired_date'] ?? ''),
            'device_code' => (string)($data['device_code'] ?? ''),
            'corporate_phone' => (string)($data['corporate_phone'] ?? ''),
            'contact_email' => (string)($data['contact_email'] ?? ''),
            'qq' => (string)($data['qq'] ?? ''),
            'wechat' => (string)($data['wechat'] ?? ''),
            'is_enable' => (int)($data['is_enable'] ?? 1),
            'remark' => (string)($data['remark'] ?? ''),
            'agreement_templates' => $templates,
            'type' => (int)($data['type'] ?? 1),
            'corporate' => (string)($data['corporate'] ?? ''),
            'registered_address' => (string)($data['registered_address'] ?? ''),
        ];
    }

    /**
     * 协议母版只保留完整可用的模板，避免创建应用时生成空协议。
     */
    public function normalizeAgreementTemplates($templates): array
    {
        if (is_string($templates)) {
            $templates = json_decode($templates, true) ?: [];
        }

        if (!is_array($templates)) {
            return [];
        }

        $result = [];
        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }
            $title = trim((string)($template['title'] ?? ''));
            $type = (string)($template['type'] ?? '');
            $content = (string)($template['content'] ?? '');
            if ($title === '' || $type === '' || $content === '') {
                continue;
            }
            $result[] = [
                'title' => $title,
                'type' => $type,
                'platform' => (string)($template['platform'] ?? 'all'),
                'content' => $content,
                'status' => (int)($template['status'] ?? 1),
                'remark' => (string)($template['remark'] ?? ''),
            ];
        }

        return $result;
    }

}
