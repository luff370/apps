<?php

namespace App\Services\System;

use App\Services\Service;
use App\Dao\System\MallAppDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use Illuminate\Support\Facades\Route as Url;

/**
 * 应用service
 * Class StoreBrandServices
 *
 * @package App\Services\System\admin
 */
class MallAppServices extends Service
{
    /**
     * form表单创建
     *
     * @var FormBuilder
     */
    protected $builder;

    /**
     * StoreBrandServices constructor.
     */
    public function __construct(MallAppDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * 创建应用表单
     *
     * @param array $formData
     *
     * @return mixed
     */
    public function createUpdateForm(array $formData = [])
    {
        $f[] = $this->builder->input('name', '应用名称', $formData['name'] ?? '')->required('请填写应用名称');
        $f[] = $this->builder->frameImage('pic', '应用图标(180*180)', url('admin/widget.images/index', ['fodder' => 'pic']), $formData['pic'] ?? '')->icon('ios-add')->width('950px')->height('505px')->modal(['footer-hide' => true]);
        $f[] = $this->builder->number('sort', '排序', (int) ($formData['sort'] ?? 0))->min(0)->precision(0);
        $f[] = $this->builder->radio('is_enable', '状态', $formData['is_enable'] ?? 1)->options([['label' => '显示', 'value' => 1], ['label' => '隐藏', 'value' => 0]]);

        return $f;
    }

    /**
     * 添加应用form表单获取
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm()
    {
        return create_form('应用添加', $this->createUpdateForm(), url('/admin/product/brand'));
    }

    /**
     * 创建应用
     *
     * @param array $data
     *
     * @return mixed
     */
    public function store(array $data)
    {
        return \DB::transaction(function () use ($data) {
            if ($res = $this->dao->save($data)) {
                return $res;
            } else {
                throw new AdminException(100022);
            }
        });
    }

    /**
     * 修改应用表单
     *
     * @param int $id
     *
     * @return array
     */
    public function updateForm(int $id)
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        return create_form('应用修改', $this->createUpdateForm($info->toArray()), url('/admin/product/brand/' . $id), 'PUT');
    }

    /**
     * 修改应用
     *
     * @param int $id
     * @param array $data
     *
     * @return bool
     */
    public function save(int $id, array $data): bool
    {
        if (!$info = $this->dao->get($id)) {
            throw new AdminException(400594);
        }

        $info->name = $data['name'];
        $info->pic = $data['pic'];
        $info->is_enable = $data['is_enable'];
        $info->save();

        return true;
    }

    /**
     * 修改应用状态
     *
     * @param int $id
     *
     * @return boolean
     */
    public function setShow(int $id, $is_enable): bool
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(400594);
        }

        $updateData = ['is_enable' => $is_enable];
        $this->dao->update($id, $updateData);

        return true;
    }
}
