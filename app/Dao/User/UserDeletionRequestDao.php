<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserDeletionRequest;
use Illuminate\Database\Eloquent\Builder;

class UserDeletionRequestDao extends BaseDao
{
    protected function setModel(): string
    {
        return UserDeletionRequest::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', (int) $where['status']);
        }

        if (!empty($where['time'])) {
            $this->searchTime($query, 'create_time', $where['time']);
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string) $where['keyword']);
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('identifier', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $query->orWhere('user_id', (int) $keyword)
                        ->orWhere('id', (int) $keyword);
                }
            });
        }

        return $query;
    }
}
