<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\SystemApp;
use Illuminate\Database\Eloquent\Builder;

class AppsDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return SystemApp::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['mer_id'])) {
            $query->where('mer_id', $where['mer_id']);
        }

        if (isset($where['is_enable']) && $where['is_enable'] !== '' ) {
            $query->where('is_enable', $where['is_enable']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('name', 'like', "{$where['keyword']}%")
                    ->orWhere('package_name', 'like', "{$where['keyword']}%");
            });
        }

        return $query;
    }
}
