<?php

declare (strict_types = 1);

namespace App\Dao\System\Attachment;

use App\Dao\BaseDao;
use App\Models\SystemAttachmentCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 *
 * Class SystemAttachmentCategoryDao
 *
 * @package App\Dao\attachment
 */
class SystemAttachmentCategoryDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemAttachmentCategory::class;
    }

    /**
     * 获取列表
     *
     * @param array $where
     *
     * @return array
     */
    public function getList(array $where)
    {
        return $this->search($where)->get()->toArray();
    }

    /**
     * 获取数量
     *
     * @param array $where
     *
     * @return int
     */
    public function getCount(array $where): int
    {
        return $this->search($where)->count();
    }

    /**
     * 搜索附件分类search
     *
     * @param array $where
     * @return Builder
     */
    public function search(array $where = []): \Illuminate\Database\Eloquent\Builder
    {
        return parent::search($where)->when(isset($where['id']), function ($query) use ($where) {
            $query->whereIn('id', $where['id']);
        });
    }
}
