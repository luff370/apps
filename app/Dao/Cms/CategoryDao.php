<?php

namespace App\Dao\Cms;

use App\Dao\BaseDao;
use App\Models\ContentCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * 文章分类
 * Class ArticleCategoryDao
 *
 * @package App\Dao\Article
 */
class CategoryDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return ContentCategory::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['pid'])) {
            $query->where('pid', $where['pid']);
        }

        if (!empty($where['column'])) {
            $query->where('column', $where['column']);
        }

        return $query;
    }

    /**
     * 获取文章列表
     *
     * @param array $where
     * @param int $page
     * @param int $limit
     *
     * @return mixed
     */
    public function getList(array $where, int $page = 0, int $limit = 0)
    {
        return $this->search($where)
            ->when(!$page && !$limit, function ($query) use ($page, $limit) {
                $query->offset(($page - 1) * $limit)->limit($limit);
            })
            ->orderByRaw('sort desc,id desc')
            ->get()
            ->toArray();
    }

    /**
     * 前台获取文章分类
     *
     * @return array
     */
    public function getArticleCategory()
    {
        return $this->search(['hidden' => 0, 'is_del' => 0, 'status' => 1, 'pid' => 0])->with(['children'])
            ->orderByRaw('sort DESC')
            ->select('id,pid,title')
            ->get()
            ->toArray();
    }

    /**
     * 二级文章分类
     *
     * @return array
     */
    public function getArticleTwoCategory()
    {
        return $this->getModel()->newQuery()
            ->where('hidden', 0)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('pid', '>', 0)
            ->orderByRaw('sort DESC')
            ->select('id,pid,title')
            ->get()
            ->toArray();
    }

    /**
     * 添加修改选择上级分类列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getMenus(array $where)
    {
        return $this->search($where)
            ->orderByRaw('sort desc,id desc')
            ->selectRaw('title,pid,id')
            ->get()
            ->toArray();
    }

    public function getTreeList(array $where, array $field): array
    {
        return $this->newQuery()->where($where)->select($field)->orderByRaw('sort desc,id desc')->get()->toArray();
    }
}
