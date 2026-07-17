<?php

namespace App\Dao\App;

use App\Dao\BaseDao;
use App\Models\AppConfig;
use Illuminate\Database\Eloquent\Builder;

class AppConfigDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return AppConfig::class;
    }


    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        return $query;
    }

    public function existsByUniqueKey(array $data, int $ignoreId = 0): bool
    {
        return $this->newQuery()
            ->where('app_id', $data['app_id'])
            ->where('channel', $data['channel'])
            ->where('version', $data['version'])
            ->where('key', $data['key'])
            ->when($ignoreId > 0, function (Builder $query) use ($ignoreId) {
                $query->where('id', '<>', $ignoreId);
            })
            ->exists();
    }
}
