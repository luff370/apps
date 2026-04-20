<?php

namespace App\Services\Merchant;

use App\Dao\Merchant\MerchantDao;
use App\Exceptions\AdminException;
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
        foreach ($list as &$item) {
            $item['type_name'] = '';
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
        return create_form('添加', $this->createUpdateForm(), url('/admin/merchant/merchant'));
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

        return create_form('修改', $this->createUpdateForm($info->toArray()), url('/admin/merchant/merchant/' . $id), 'PUT');
    }

    /**
     * 生成form表单
     */
    public function createUpdateForm(array $info = []): array
    {
        $f[] = Form::text('name', '公司名称', $info['name'] ?? '');
        $f[] = Form::text('domain', '商户域名', $info['domain'] ?? '');
        $f[] = Form::radio('type', '企业类型：1-有限责任公司，2-个体工商户，3-个人', $info['type'] ?? '1')->options();
        $f[] = Form::text('corporate', '企业法人', $info['corporate'] ?? '');
        $f[] = Form::textarea('registered_address', '注册地址', $info['registered_address'] ?? '');
        $f[] = Form::number('create_time', 'create_time', $info['create_time'] ?? '');
        $f[] = Form::number('update_time', 'update_time', $info['update_time'] ?? '');

        return $f;
    }

}
