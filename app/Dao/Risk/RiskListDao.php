<?php

namespace App\Dao\Risk;

use App\Dao\BaseDao;
use App\Models\RiskList;
use Illuminate\Database\Eloquent\Builder;

class RiskListDao extends BaseDao
{
    protected function setModel(): string
    {
        return RiskList::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['list_type'])) {
            $query->where('list_type', $where['list_type']);
        }
        if (!empty($where['target_type'])) {
            $query->where('target_type', $where['target_type']);
        }
        if ($where['app_id'] !== '' && $where['app_id'] !== null && $where['app_id'] !== false) {
            $query->where('app_id', (int) $where['app_id']);
        }
        if (!empty($where['keyword'])) {
            $keyword = $where['keyword'];
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('target_value', 'like', '%' . $keyword . '%')
                    ->orWhere('remark', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }
}
