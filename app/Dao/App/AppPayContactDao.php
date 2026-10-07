<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppPayContact;
use Illuminate\Database\Eloquent\Builder;

class AppPayContactDao extends BaseDao
{
    protected function setModel(): string
    {
        return AppPayContact::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $appId = (int) $where['app_id'];
            $query->whereHas('apps', function (Builder $builder) use ($appId) {
                $builder->where('system_apps.id', $appId);
            });
        }

        $keyword = trim((string) ($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $query->where('is_enable', (int) $where['is_enable']);
        }

        return $query;
    }
}
