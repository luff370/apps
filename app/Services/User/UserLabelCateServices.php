<?php

namespace App\Services\User;

use App\Services\Service;
use App\Dao\Other\CategoryDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormBuilder;
use App\Support\Services\CacheService;

/**
 * Class UserLabelCateServices
 *
 * @package App\Services\User
 * @method delete($id, ?string $key = null) 删除
 * @method update($id, array $data, ?string $key = null) 更新数据
 * @method save(array $data) 保存数据
 * @method array|Model|null get($id, ?array $field = [], ?array $with = []) 获取一条数据
 * @method getAllLabel(array $with = []) 获取全部标签分类
 */
class UserLabelCateServices extends Service
{
    /**
     * 标签分类缓存
     *
     * @var string
     */
    protected $cacheName = 'label_list_all';

    /**
     * UserLabelCateServices constructor.
     *
     * @param CategoryDao $dao
     */
    public function __construct(CategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取标签分类
     *
     * @param array $where
     *
     * @return array
     */
    public function getLabelList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getCateList($where, $page, $limit);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 删除分类缓存
     *
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteCateCache()
    {
        return CacheService::delete($this->cacheName);
    }

    /**
     * 获取标签全部分类
     *
     * @return bool|mixed|null
     */
    public function getLabelCate()
    {
        return CacheService::get($this->cacheName, function () {
            return $this->dao->getCateList(['type' => 0]);
        });
    }

    /**
     * 标签分类表单
     *
     * @param array $cataData
     *
     * @return mixed
     */
    public function labelCateForm(array $cataData = [])
    {
        $f[] = FormBuilder::input('name', '分类名称', $cataData['name'] ?? '')->required();
        $f[] = FormBuilder::number('sort', '排序', (int) ($cataData['sort'] ?? 0));

        return $f;
    }

    /**
     * 创建表单
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm()
    {
        return create_form('添加标签分类', $this->labelCateForm(), url('/admin/user/user_label_cate'), 'POST');
    }

    /**
     * 修改分类标签表单
     *
     * @param int $id
     *
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function updateForm(int $id)
    {
        $labelCate = $this->dao->get($id);
        if (!$labelCate) {
            throw new AdminException(100026);
        }

        return create_form('编辑标签分类', $this->labelCateForm($labelCate->toArray()), url('/adminuser/user_label_cate/' . $id), 'PUT');
    }

    /**
     * 用户标签列表
     *
     * @param int $uid
     *
     * @return array
     */
    public function getUserLabel(int $uid)
    {
        $list = $this->dao->getAllLabel(['type' => 0], ['label']);
        /** @var UserLabelRelationServices $services */
        $services = app(UserLabelRelationServices::class);
        $labelIds = $services->getUserLabels($uid) ?? [];
        foreach ($list as $key => &$item) {
            if (is_array($item['label'])) {
                if (!$item['label']) {
                    unset($list[$key]);
                    continue;
                }
                foreach ($item['label'] as &$value) {
                    if (in_array($value['id'], $labelIds)) {
                        $value['disabled'] = true;
                    } else {
                        $value['disabled'] = false;
                    }
                }
            }
        }

        return array_merge($list);
    }
}
