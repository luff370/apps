<?php

namespace App\Services\System\Store;

use App\Services\Service;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use App\Dao\System\Store\SystemStoreStaffDao;

/**
 * 门店店员
 * Class SystemStoreStaffServices
 *
 * @package App\Services\System\Store
 * @mixin SystemStoreStaffDao
 */
class SystemStoreStaffServices extends Service
{
    /**
     * @var FormBuilder
     */
    protected $builder;

    /**
     * 构造方法
     * SystemStoreStaffServices constructor.
     *
     * @param SystemStoreStaffDao $dao
     * @param FormBuilder $builder
     */
    public function __construct(SystemStoreStaffDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * 判断是否是有权限核销的店员
     *
     * @param $uid
     *
     * @return bool
     */
    public function verifyStatus($uid)
    {
        return (bool) $this->dao->getOne(['uid' => $uid, 'status' => 1, 'verify_status' => 1]);
    }

    /**
     * 获取店员列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getStoreStaffList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getStoreStaffList($where, $page, $limit);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 获取select选择框中的门店列表
     *
     * @return array
     */
    public function getStoreSelectFormData()
    {
        /** @var SystemStoreServices $service */
        $service = app(SystemStoreServices::class);
        $menus = [];
        foreach ($service->getStore() as $menu) {
            $menus[] = ['value' => $menu['id'], 'label' => $menu['name']];
        }

        return $menus;
    }

    /**
     * 获取核销员表单
     *
     * @param array $formData
     *
     * @return mixed
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createStoreStaffForm(array $formData = [])
    {
        if ($formData) {
            $field[] =
                $this->builder->frameImage('image', '更换头像', url('/adminadmin/widget.images/index', ['fodder' => 'image'], true), $formData['avatar'] ?? '')
                    ->icon('ios-add')
                    ->width('950px')
                    ->height('505px')
                    ->modal(['footer-hide' => true]);
        } else {
            $field[] =
                $this->builder->frameImage('image', '商城用户', url('/adminadmin/system.User/list', ['fodder' => 'image'], true))
                    ->icon('ios-add')
                    ->width('950px')
                    ->height('505px')
                    ->modal(['footer-hide' => true])
                    ->Props(['srcKey' => 'image']);
        }
        $field[] = $this->builder->hidden('uid', $formData['uid'] ?? 0);
        $field[] = $this->builder->hidden('avatar', $formData['avatar'] ?? '');
        $field[] = $this->builder->select('store_id', '所属提货点', ($formData['store_id'] ?? 0))->setOptions($this->getStoreSelectFormData())->filterable(true);
        $field[] = $this->builder->input('staff_name', '核销员名称', $formData['staff_name'] ?? '')->col(24)->required();
        $field[] = $this->builder->input('phone', '手机号码', $formData['phone'] ?? '')->col(24)->required();
        $field[] = $this->builder->radio('verify_status', '核销开关', $formData['verify_status'] ?? 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);
        $field[] = $this->builder->radio('status', '状态', $formData['status'] ?? 1)->options([['value' => 1, 'label' => '开启'], ['value' => 0, 'label' => '关闭']]);

        return $field;
    }

    /**
     * 添加核销员表单
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm()
    {
        return create_form('添加核销员', $this->createStoreStaffForm(), url('/admin/merchant/store_staff/save/0'));
    }

    /**
     * 编辑核销员form表单
     *
     * @param int $id
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function updateForm(int $id)
    {
        $storeStaff = $this->dao->get($id);
        if (!$storeStaff) {
            throw new AdminException(100026);
        }

        return create_form('修改核销员', $this->createStoreStaffForm($storeStaff->toArray()), url('/admin/merchant/store_staff/save/' . $id));
    }
}
