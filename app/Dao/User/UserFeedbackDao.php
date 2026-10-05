<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserFeedback;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class UserFeedbackDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserFeedback::class;
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

        if (isset($where['status']) && $where['status'] !== '') {
            if ((int) $where['status'] === 1) {
                $query->where('status', 1);
            } else {
                $query->where('status', '<>', 1);
            }
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string) $where['keyword']);
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('email', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhereHas('user', function (Builder $query) use ($keyword) {
                        $query->where('account', 'like', '%' . $keyword . '%');
                    });
                if (ctype_digit($keyword) && (int) $keyword <= 4294967295) {
                    $query->orWhere('user_id', (int) $keyword);
                }
            });
        }

        if (!empty($where['time']) && is_string($where['time']) && str_contains($where['time'], '-')) {
            [$startTime, $endTime] = explode('-', $where['time'], 2);
            $startTime = trim($startTime);
            $endTime = trim($endTime);
            if ($startTime !== '' && $endTime !== '') {
                $query->whereBetween('create_time', [
                    Carbon::parse($startTime)->timestamp,
                    Carbon::parse($endTime)->timestamp,
                ]);
            }
        }

        return $query;
    }
}
