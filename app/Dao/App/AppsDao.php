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
        $query->where("is_del", 0);

        if (!empty($where['mer_id'])) {
            $query->where('mer_id', $where['mer_id']);
        }

        if (!empty($where['platform'])) {
            $query->where('platform', $where['platform']);
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
