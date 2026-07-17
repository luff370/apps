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
        $f[] = Form::text('api_domain', '接口域名', $info['api_domain'] ?? '')->required();
        $f[] = Form::text('image_domain', '图片域名', $info['image_domain'] ?? '')->required();
        $f[] = Form::text('server_subject', '服务器主体', $info['server_subject'] ?? '');
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
        $this->validateMerchantData($saveData);
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
            'api_domain' => (string)($data['api_domain'] ?? ''),
            'image_domain' => (string)($data['image_domain'] ?? ''),
            'server_subject' => (string)($data['server_subject'] ?? ''),
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
     * 商户主体核心域名由后端兜底校验。
     *
     * 前端表单会做 required 校验，但这些字段会影响新增应用时的主体配置和后续接口/图片访问，
     * 不能只依赖浏览器校验；接口直调或旧页面提交时也必须拦住不完整数据。
     *
     * @throws AdminException
     */
    private function validateMerchantData(array $data): void
    {
        if (trim((string)($data['name'] ?? '')) === '') {
            throw new AdminException('请输入公司名称');
        }
        if (trim((string)($data['corporate'] ?? '')) === '') {
            throw new AdminException('请输入法人名称');
        }
        if (trim((string)($data['domain'] ?? '')) === '') {
            throw new AdminException('请输入商户域名');
        }
        if (trim((string)($data['domain_expired_date'] ?? '')) === '') {
            throw new AdminException('请选择域名到期时间');
        }
        if (trim((string)($data['api_domain'] ?? '')) === '') {
            throw new AdminException('请输入接口域名');
        }
        if (trim((string)($data['image_domain'] ?? '')) === '') {
            throw new AdminException('请输入图片域名');
        }
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
