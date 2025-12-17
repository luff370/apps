<?php

namespace App\Dao\Cms;

use App\Dao\BaseDao;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;

/**
 * 文章dao
 * Class ArticleDao
 *
 * @package App\Dao\Article
 */
class ContentDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return Article::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['cate_id'])) {
            $query->where('cate_id', $where['cate_id']);
        }

        if (!empty($where['column'])) {
            $query->where('column', $where['column']);
        }

        if (isset($where['ids']) && is_array($where['ids'])) {
            $query->whereIn('id', $where['ids']);
        }

        if (!empty($where['title'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('title', 'like', "%{$where['title']}%")
                    ->orWhere('keyword', 'like', "%{$where['title']}%");
            });
        }

        return $query;
    }
}
