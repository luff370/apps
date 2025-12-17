<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserWhitelist;
use App\Models\UserWhitelistLog;
use Illuminate\Database\Eloquent\Builder;

class UserWhitelistLogDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserWhitelistLog::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['market_channel'])) {
            $query->where('market_channel', $where['market_channel']);
        }

        if (!empty($where['source_type'])) {
            $query->where('source_type', $where['source_type']);
        }

        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function ($query) use ($where) {
                $query->where('source', 'like', $where['keyword'] . "%")
                    ->orWhere('region', 'like', $where['keyword'] . "%")
                    ->orWhere('ip', 'like', $where['keyword'] . "%")
                    ->orWhere('uuid', $where['keyword'])
                    ->orWhere('device', $where['keyword']);
            });
        }

        return $query;
    }

}
