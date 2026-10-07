<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppNotice;
use Illuminate\Database\Eloquent\Builder;

class AppNoticeDao extends BaseDao
{
    protected function setModel(): string
    {
        return AppNotice::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', (int) $where['is_enable']);
        }

        return $query;
    }
}
