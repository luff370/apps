<?php

namespace App\Dao\User;

use App\Dao\BaseDao;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Builder;

class UserArchiveDao extends BaseDao
{
    /**
     * 设置模型
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserProfile::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery();

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }

        $version = $where['app_version'] ?? ($where['version'] ?? '');
        if ($version !== '') {
            $query->where('version', 'like', $version . '%');
        }

        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }

        if (!empty($where['keyword'])) {
            $keyword = trim((string) $where['keyword']);
            $query->where(function (Builder $query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('birth_place', 'like', '%' . $keyword . '%')
                    ->orWhere('uuid', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $query->orWhere('user_id', (int) $keyword);
                }
            });
        }

        if (isset($where['is_login']) && $where['is_login'] !== '' && $where['is_login'] !== null) {
            if ((int) $where['is_login'] === 1) {
                $query->where('user_id', '>', 0);
            } else {
                $query->where(function (Builder $query) {
                    $query->where('user_id', 0)->orWhereNull('user_id');
                });
            }
        }

        return $query;
    }
}
