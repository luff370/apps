<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserWhitelist;
use Illuminate\Database\Eloquent\Builder;

class UserWhitelistDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserWhitelist::class;
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

        if (!empty($where['way'])) {
            $query->where('way', $where['way']);
        }

        if (!empty($where['type'])) {
            $query->whereRaw('type & ?', $where['type']);
        }

        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }

        if (!empty($where['keyword'])) {
            $query->where('content', 'like', $where['keyword'] . "%")
                ->orWhere('remark', 'like', "%" . $where['keyword'] . "%");
        }

        return $query;
    }
}
