<?php

namespace App\Dao\Finance;

use App\Dao\BaseDao;
use App\Models\UserWithdrawal;
use Illuminate\Database\Eloquent\Builder;

class UserWithdrawalDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserWithdrawal::class;
    }

    public function getAuditSuccessCount($userId, $appId = null): int
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('audit_status', UserWithdrawal::AUDIT_STATUS_SUCCESS)
            ->when($appId, function ($query) use ($appId) {
                $query->where('app_id', $appId);
            })
            ->count();
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (!empty($where['account_type'])) {
            $query->where('account_type', $where['account_type']);
        }

        if (isset($where['audit_status']) && $where['audit_status'] !== '') {
            $query->where('audit_status', $where['audit_status']);
        }

        if (!empty($where['keyword'])) {
            $query->where(function (Builder $query) use ($where) {
                $query->where('user_id', 'like', "{$where['keyword']}%")
                    ->orWhere('account', 'like', "{$where['keyword']}%")
                    ->orWhere('account_name', 'like', "{$where['keyword']}%");
            });
        }

        if (!empty($where['time'])) {
            $query = $this->searchDate($query, 'created_at', $where['time']);
        }

        return $query;
    }
}
