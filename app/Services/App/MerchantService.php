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
        return create_form('添加', $this->createUpdateForm(), url('/admin/app/merchant'));
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

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/app/merchant/' . $id), 'PUT');
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

}
