<?php

namespace App\Services\Cms;

use App\Models\Article;
use App\Services\Service;
use App\Models\SystemApp;
use App\Dao\Cms\CategoryDao;
use App\Exceptions\AdminException;
use App\Support\Services\FormOptions;
use App\Support\Services\FormBuilder as Form;

/**
 * Class ArticleCategoryServices
 *
 * @package app\services\article
 * @method getArticleCategory()
 * @method getArticleTwoCategory()
 */
class CategoryService extends Service
{
    public function __construct(CategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取文章分类列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList($where, $page, $limit);

        $apps = SystemApp::query()->pluck('name', 'id');
        foreach ($list as &$item) {
            $item['app_name'] = $apps[$item['app_id']] ?? '';
        }

        $list = get_tree_children($list);
        $count = $this->dao->count($where);

        return compact('list', 'count');
    }

    /**
     * 生成创建修改表单
     *
     * @param int $id
     *
     * @return array
     * @throws \App\Exceptions\AdminException
     */
    public function createForm(int $id, int $appId = 0)
    {
        logger()->info('---app_id---', [$appId]);
        $method = 'POST';
        $url = '/admin/cms/category';
        if ($id) {
            $info = $this->dao->get($id);
            $method = 'PUT';
            $url = $url . '/' . $id;
            $pid = $info['pid'];
            $appId = $info['app_id'];
        } else {
            $pid = '';
        }
        $f = [];
        $f[] = Form::hidden('id', $info['id'] ?? 0);
        $f[] = Form::select('app_id', '应用名称', ($appId))->setOptions(FormOptions::systemApps())->filterable(false)->disabled(true);
        $f[] = Form::select('pid', '上级分类', (int) ($info['pid'] ?? ''))->setOptions($this->menus($pid, $appId))->filterable(true);
        $f[] = Form::input('title', '分类名称', $info['title'] ?? '')->maxlength(20)->required();
        $f[] = Form::input('column', '分类编码', $info['column'] ?? '')->maxlength(20)->required();
        $f[] = Form::input('intr', '分类简介', $info['intr'] ?? '')->type('textarea');
        $f[] = Form::frameImage('image', '分类图片', '/admin/widget.images/index.html?fodder=image', $info['image'] ?? '')->width('950px')->height('560px');
        $f[] = Form::number('sort', '排序', (int) ($info['sort'] ?? 0))->precision(0);
        $f[] = Form::radio('status', '状态', $info['status'] ?? 1)->options([['value' => 1, 'label' => '显示'], ['value' => 0, 'label' => '隐藏']]);

        return create_form('添加分类', $f, url($url), $method);
    }

    /**
     * 保存
     *
     * @param array $data
     *
     * @return mixed
     */
    public function save(array $data)
    {
        return $this->dao->save($data);
    }

    /**
     * 修改
     *
     * @param array $data
     *
     * @return mixed
     */
    public function update(array $data)
    {
        return $this->dao->update($data['id'], $data);
    }

    /**
     * 删除
     *
     * @param int $id
     *
     * @return mixed
     * @throws \App\Exceptions\AdminException
     */
    public function del(int $id)
    {
        $pidCount = $this->dao->newQuery()->where(['pid' => $id])->count();
        if ($pidCount > 0) {
            throw new AdminException(400454);
        }
        $count = Article::query()->where(['cate_id' => $id])->count();
        if ($count > 0) {
            throw new AdminException(400455);
        } else {
            return $this->dao->delete($id);
        }
    }

    /**
     * 修改状态
     *
     * @param int $id
     * @param int $status
     *
     * @return mixed
     */
    public function setStatus(int $id, int $status)
    {
        return $this->dao->update($id, ['status' => $status]);
    }

    /**
     * 获取一级分类组合数据
     *
     * @param string $pid
     *
     * @return array[]
     */
    public function menus($pid = '', $appId='')
    {
        $list = $this->dao->getMenus(['pid' => 0, 'app_id'=>$appId]);
        $menus = [['value' => 0, 'label' => '顶级分类']];
        if ($pid === 0) {
            return $menus;
        }
        if ($pid != '') {
            $menus = [];
        }
        foreach ($list as $menu) {
            $menus[] = ['value' => $menu['id'], 'label' => $menu['title']];
        }

        return $menus;
    }

    /**
     * 树形列表
     *
     * @return array
     */
    public function getTreeList($args=[])
    {
        $args = array_merge($args, ['is_del' => 0, 'status' => 1]);
        return sort_list_tier($this->dao->getTreeList($args, ['id', 'id as value', 'title as label', 'title', 'pid']));
    }
}
