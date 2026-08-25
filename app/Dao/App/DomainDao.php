<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppDomain;
use Illuminate\Database\Eloquent\Builder;

class DomainDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return AppDomain::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', (int) $where['status']);
        }

        if (isset($where['risk_level']) && $where['risk_level'] !== '') {
            $query->where('risk_level', (int) $where['risk_level']);
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string) $where['keyword']);
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('domain', 'like', '%' . $keyword . '%')
                    ->orWhere('subject', 'like', '%' . $keyword . '%')
                    ->orWhere('remark', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }
}
