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

    // Bulk SQL updates/deletes bypass model events and would lose parameter audit records.
    public function update($id, array $data, string $key = ''): int
    {
        $where = is_array($id) ? $id : [($key ?: $this->getPk()) => $id];

        return $this->getModel()->getConnection()->transaction(function () use ($where, $data) {
            $configs = $this->newQuery()->where($where)->lockForUpdate()->get();
            foreach ($configs as $config) {
                $config->fill($data)->save();
            }
            return $configs->count();
        });
    }

    public function delete($id, string $key = null)
    {
        $where = is_array($id) ? $id : [($key ?? $this->getPk()) => $id];

        return $this->getModel()->getConnection()->transaction(function () use ($where) {
            $configs = $this->newQuery()->where($where)->lockForUpdate()->get();
            foreach ($configs as $config) {
                $config->delete();
            }
            return $configs->count();
        });
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
            ->where('key', $data['key'])
            ->when($ignoreId > 0, function (Builder $query) use ($ignoreId) {
                $query->where('id', '<>', $ignoreId);
            })
            ->exists();
    }
}
